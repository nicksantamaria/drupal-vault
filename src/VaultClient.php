<?php

namespace Drupal\vault;

use Vault\CachedClient;

/**
 * Wrapper for \Vault\Client providing some helper methods.
 */
class VaultClient extends CachedClient {

  public const API = 'v1';

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

}
