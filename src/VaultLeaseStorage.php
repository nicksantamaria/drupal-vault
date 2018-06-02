<?php

namespace Drupal\vault;

class VaultLeaseStorage {

  public const storageNamePrefix = "vault:lease";

  public static function storageName($suffix) {
    return sprintf("%s:%s", self::storageNamePrefix, $suffix);
  }

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $storage;

  /**
   * VaultLeaseStorage constructor.
   */
  public function __construct() {
    $this->storage = \Drupal::keyValueExpirable("vault_lease");
  }

  /**
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   *
   * @return mixed
   */
  public function getLease($storage_key) {
    $item = $this->storage->get(self::storageName($storage_key));
    return $item['data'];
  }

  /**
   * @return array
   */
  public function getAllLeases() {
    $items = $this->storage->getAll();
    $returned = [];
    foreach ($items as $key => $item) {
      $returned[$key] = $item['data'];
    }
    return $returned;
  }

  /**
   * Returns the lease ID for a given storage key.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   *
   * @return string
   *  The Vault lease ID.
   */
  public function getLeaseId($storage_key) {
    $item = $this->storage->get(self::storageName($storage_key));
    return $item['lease_id'];
  }

  /**
   * Deletes a lease from storage.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   */
  public function deleteLease($storage_key) {
    $this->storage->delete(self::storageName($storage_key));
  }

  /**
   * Stores a new lease.
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
  public function setLease($storage_key, $lease_id, $data, $expires) {
    $payload = [
      'lease_id' => $lease_id,
      'data' => $data,
    ];
    $this->storage->setWithExpire(self::storageName($storage_key), $payload, $expires);
  }

  /**
   * Updates the expiry of an existing lease.
   *
   * @param $storage_key string
   *  The storage key. Something like "key:key_machine_id".
   * @param $expires
   *  The lease expiry.
   */
  public function updateLeaseExpires($storage_key, $new_expires) {
    $data = $this->getLease(self::storageName($storage_key));
    $this->setLease(self::storageName($storage_key), $data['lease_id'], $data['data'], $new_expires);
  }

}