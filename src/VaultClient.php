<?php

namespace Drupal\vault;

use Vault\CachedClient;

/**
 * Wrapper for \Vault\Client providing some helper methods.
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
   * @param $lease_id string
   *  The lease ID.
   * @param $data
   *  The lease data.
   * @param $expires
   *  The lease expiry.
   */
  public function storeLease($lease_id, $data, $expires) {
    $this->leaseStorage->setLease($lease_id, $data, $expires);
  }

  /**
   * Revokes a lease.
   */
  public function revokeLease($lease_id) {
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

  public function renewLease($lease_id, $increment = 86400) {
    try {
      $response = $this->put("/sys/leases/renew", ["lease_id" => $lease_id, "increment" => $increment]);
      $new_expires = \Drupal::time()->getRequestTime() + (int) $response->getLeaseDuration();
      $this->leaseStorage->updateLeaseExpires($new_expires);
    }
    catch (\Exception $e) {
      $this->logger->error(sprintf("Failed renewing lease %s", $lease_id));
    }
  }

}
