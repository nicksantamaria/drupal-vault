<?php

namespace Drupal\vault;

use Vault\CachedClient;
use Vault\Exceptions\ClientException;
use Drupal\Component\Serialization\Json;

/**
 * Wrapper for \Vault\Client providing some helper methods.
 *
 * It also acts as a translation layer between vault leases and drupal entities
 * implementing leases (like keys).
 */
class VaultClient extends CachedClient {

  public const API = 'v1';

  /**
   * @var \Drupal\Vault\VaultLeaseStorage
   */
  protected $leaseStorage;

  /**
   * @param $leaseManager \Drupal\Vault\VaultLeaseStorage
   */
  public function setLeaseStorage($leaseStorage) {
    $this->leaseStorage = $leaseStorage;
  }

  /**
   * Makes a LIST request against an endpoint.
   *
   * @param string $url
   *   Request URL.
   * @param array $options
   *   Options to pass to request.
   *
   * @return \Vault\ResponseModels\Response
   *   Response from vault server.
   *
   * @todo implement this in upstream class.
   */
  public function list($url, array $options = []) {
    return $this->responseBuilder->build($this->send(new Request('LIST', $url), $options));
  }

  /**
   * Queries list of secret engine mounts on the configured vault instance.
   *
   * @return \Vault\ResponseModels\Response
   *   Response from vault server.
   */
  public function listMounts() {
    return $this->read('/sys/mounts')->getData();
  }

  /**
   * Queries list of particular secret backends.
   *
   * @param array $engine_types
   *   Array of secret engine types to list.
   *
   * @return array
   *   Array of secret engine mounts.
   */
  public function listSecretEngineMounts(array $engine_types) {
    $data = $this->listMounts();
    return array_filter($data, function ($v) use ($engine_types) {
      return in_array($v['type'], $engine_types);
    });
  }

  /**
   * Stores a lease.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   * @param $lease_id string
   *  The lease ID.
   * @param $data
   *  The lease data.
   * @param $expires
   *  The lease expiry.
   */
  public function storeLease($storage_key, $lease_id, $data, $expires) {
    $this->leaseStorage->setLease($storage_key, $lease_id, $data, $expires);
  }

  /**
   * Retrieve a lease.
   */
  public function retrieveLease($storage_key) {
    return $this->leaseStorage->getLease($storage_key);
  }

  /**
   * Revokes a lease.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   */
  public function revokeLease($storage_key) {
    // @todo make revoke request. Something like this:
//    try {
//      // @todo for some reason these tokens aren't being revoked. Get to the bottom of it.
//      $path = '/sys/leases/revoke';
//      $response = $this->client->put($path, ["lease_id" => $lease['lease_id']]);
//    } catch (Exception $e) {
//      $this->logger->critical('Unable to revoke lease on secret ' . $key->id());
//    }
    //$this->revoke()
    $this->leaseStorage->deleteLease($lease_id);
  }

  /**
   * Renews a lease.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   * @param int $increment
   *  The number of seconds to extend the release by.
   *
   * @return bool
   *  TRUE if successful, FALSE if failed.
   */
  public function renewLease($storage_key, $increment) {
    $this->logger->debug(sprintf("attempting to renew lease for %s", $storage_key));
    $lease_id = $this->leaseStorage->getLeaseId($storage_key);
    try {
      if (empty($lease_id)) {
        throw new ClientException("no valid lease for " . $storage_key);
      }

      $data = [
        "lease_id" => $lease_id,
        "increment" => $increment
      ];
      $response = $this->put($this->buildPath("/sys/leases/renew"), ['json' => $data]);
      if (is_null($response->getRequestId())) {
        throw new ClientException("null response from server renewing lease for " . $storage_key);
      }

      $new_expires = $response->getLeaseDuration();
      $this->leaseStorage->updateLeaseExpires($storage_key, $new_expires);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf("Failed renewing lease %s", $lease_id));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Helper method to renew all existing leases.
   *
   * @param $increment int
   *  Additional length to request leases for. Should be _at least_ the number
   *  of seconds between cron runs.
   */
  public function renewAllLeases($increment) {
    $leases = $this->leaseStorage->getAllLeases();
    foreach ($leases as $key => $value) {
      $response = $this->renewLease($key, $increment);
      if (!$response) {
        // Failed to renew the lease - remove it from state.
        $this->logger->info(sprintf("revoking expired lease for %s", $key));
        $this->revokeLease($key);
      }
    }
  }

}
