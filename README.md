# ReactAwareCurlFactory

[![Build Status](https://travis-ci.org/nopolabs/react-aware-guzzle-client.svg?branch=master)](https://travis-ci.org/nopolabs/react-aware-guzzle-client)
[![Code Climate](https://codeclimate.com/github/nopolabs/react-aware-guzzle-client/badges/gpa.svg)](https://codeclimate.com/github/nopolabs/react-aware-guzzle-client)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nopolabs/react-aware-guzzle-client/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nopolabs/react-aware-guzzle-client/?branch=master)
[![License](https://poser.pugx.org/nopolabs/react-aware-guzzle-client/license)](https://packagist.org/packages/nopolabs/react-aware-guzzle-client)
[![Latest Stable Version](https://poser.pugx.org/nopolabs/react-aware-guzzle-client/v/stable)](https://packagist.org/packages/nopolabs/react-aware-guzzle-client)

An implementation of GuzzleHttp\Handler\CurlFactoryInterface that plays nicely with React.

```php
public function newClient(
        LoopInterface $eventLoop,
        array $config = [],
        CurlFactory $curlFactory = null,
        LoggerInterface $logger = null
    ) : Client
{
    $clientFactory = new ReactAwareGuzzleClientFactory();
    return $clientFactory->createGuzzleClient($eventLoop, $config, null, $logger);
}
```
