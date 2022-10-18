<?php

/**
 * This file is part of web3.php package.
 *
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 *
 * @license MIT
 */

namespace Web3;

use Web3\Providers\HttpProvider;
use Web3\Providers\Provider;
use Web3\RequestManagers\HttpRequestManager;

class Web3
{
    protected Provider $provider;

    protected Eth $eth;

    protected Net $net;

    protected Personal $personal;

    protected Shh $shh;

    protected Utils $utils;

    private array $methods = [];

    private array $allowedMethods = [
        'web3_clientVersion', 'web3_sha3',
    ];

    public function __construct(Provider|string $provider)
    {
        if (is_string($provider) && (filter_var($provider, FILTER_VALIDATE_URL) !== false)) {
            // check the uri schema
            if (preg_match('/^https?:\/\//', $provider) === 1) {
                $requestManager = new HttpRequestManager($provider);

                $this->provider = new HttpProvider($requestManager);
            }
        } elseif ($provider instanceof Provider) {
            $this->provider = $provider;
        }
    }

    public function __call(string $name, array $arguments)
    {
        if (empty($this->provider)) {
            throw new \RuntimeException('Please set provider first.');
        }

        $class = explode('\\', __CLASS__);

        if (preg_match('/^[a-zA-Z0-9]+$/', $name) === 1) {
            $method = strtolower($class[1]) . '_' . $name;

            if (!in_array($method, $this->allowedMethods)) {
                throw new \RuntimeException('Unallowed rpc method: ' . $method);
            }

            if ($this->provider->isBatch) {
                $callback = null;
            } else {
                $callback = array_pop($arguments);

                if (is_callable($callback) !== true) {
                    throw new \InvalidArgumentException('The last param must be callback function.');
                }
            }

            if (!array_key_exists($method, $this->methods)) {
                // new the method
                $methodClass = sprintf("\Web3\Methods\%s\%s", ucfirst($class[1]), ucfirst($name));
                $methodObject = new $methodClass($method, $arguments);
                $this->methods[$method] = $methodObject;
            } else {
                $methodObject = $this->methods[$method];
            }

            if ($methodObject->validate($arguments)) {
                $inputs = $methodObject->transform($arguments, $methodObject->inputFormatters);
                $methodObject->arguments = $inputs;
                $this->provider->send($methodObject, $callback);
            }
        }
    }

    public function __get(string $name)
    {
        $method = 'get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], []);
        }

        return false;
    }

    public function __set(string $name, mixed $value)
    {
        $method = 'set' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$value]);
        }

        return false;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getEth(): Eth
    {
        if (!isset($this->eth)) {
            $this->eth = new Eth($this->provider);
        }

        return $this->eth;
    }

    public function getNet(): Net
    {
        if (!isset($this->net)) {
            $this->net = new Net($this->provider);
        }

        return $this->net;
    }

    public function getPersonal(): Personal
    {
        if (!isset($this->personal)) {
            $this->personal = new Personal($this->provider);
        }

        return $this->personal;
    }

    public function getShh(): Shh
    {
        if (!isset($this->shh)) {
            $this->shh = new Shh($this->provider);
        }

        return $this->shh;
    }

    public function getUtils(): Utils
    {
        if (!isset($this->utils)) {
            $this->utils = new Utils;
        }

        return $this->utils;
    }

    /**
     * batch.
     *
     * @param bool $status
     * @return void
     */
    public function batch($status): void
    {
        $status = is_bool($status);

        $this->provider->batch($status);
    }
}
