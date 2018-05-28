<?php

namespace Drupal\vault;

use Vault\Client;

class VaultClient extends Client {

  public const API = 'v1';

  /**
   * Makes a LIST request against an endpoint.
   *
   * @param string $path
   * @param array  $data
   *
   * @return \Vault\ResponseModels\Response
   *
   * @todo implement this in upstream class.
   */
  public function list($url, array $options = []) {
    return $this->responseBuilder->build($this->send(new Request('LIST', $url), $options));
  }

  /**
   * Queries list of secret engine mounts on the configured vault instance.
   *
   * @param string $url
   * @param array  $options
   *
   * @return \Vault\ResponseModels\Response
   */
  public function listMounts() {
    return $this->read('/sys/mounts')->getData();
  }

  /**
   * Queries list of particular secret backends.
   *
   * @param array $engine_types
   *
   * @return array
   */
  public function listSecretEngineMounts(array $engine_types) {
    $data = $this->listMounts();
    return array_filter($data, function($v) use($engine_types) {
      return in_array($v['type'], $engine_types);
    });
  }

}
