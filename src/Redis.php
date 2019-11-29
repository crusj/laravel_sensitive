<?php
/**
 * author crusj
 * date   2019/11/29 10:43 下午
 */


namespace Crusj\Sensitive;


use Predis\Client;

class Redis
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function del(string $key)
    {
        $this->client->executeRaw(['del', $key]);
    }

    public function setPush(string $key, string $value)
    {
        $this->client->executeRaw(['SADD', $key, $value]);
    }

    public function setGetAll(string $key): array
    {
        return $this->client->executeRaw(['SMEMBERS', $key])??[];
    }

}
