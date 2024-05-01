<?php

namespace Kevachat\Npsapp\Server;

use \Ratchet\MessageComponentInterface;

class Ratchet implements MessageComponentInterface
{
    private \Kevachat\Kevacoin\Client $_kevacoin;

    private object $_config;

    private array $_namespaces;

    public function __construct(
        object $config
    ) {
        // Init config
        $this->_config = $config;

        // Init KevaCoin
        $this->_kevacoin = new \Kevachat\Kevacoin\Client(
            $this->_config->kevacoin->server->protocol,
            $this->_config->kevacoin->server->host,
            $this->_config->kevacoin->server->port,
            $this->_config->kevacoin->server->username,
            $this->_config->kevacoin->server->password
        );

        // Validate funds
        if ((float) $this->_kevacoin->getBalance($this->_config->kevacoin->wallet->account) <= 0)
        {
            throw new \Exception(); // @TODO
        }

        // Init allowed chat rooms registry
        if ($namespaces = $this->_kevacoin->kevaListNamespaces())
        {
            $i = 1; foreach ((array) $namespaces as $namespace)
            {
                // Skip system namespaces
                if (str_starts_with($namespace['displayName'], '_'))
                {
                    continue;
                }

                // Skip blacklist
                if (in_array($namespace['namespaceId'], $this->_config->kevacoin->wallet->namespace->blacklist))
                {
                    continue;
                }

                // Check whitelist on contain records
                if (count($this->_config->kevacoin->wallet->namespace->whitelist) &&
                   !in_array($namespace['namespaceId'], $this->_config->kevacoin->wallet->namespace->whitelist))
                {
                    continue;
                }

                // Append room to the namespace registry
                $this->_namespaces[$i++] = [
                    'hash' => $namespace['namespaceId'],
                    'name' => $namespace['displayName']
                ];
            }

            // Sort rooms by name ASC
            array_multisort(
                array_column(
                    $this->_namespaces,
                    'name'
                ),
                SORT_ASC,
                SORT_STRING | SORT_NATURAL | SORT_FLAG_CASE,
                $this->_namespaces
            );
        }

        else throw new \Exception(); // @TODO

        // Dump event on enabled
        if ($this->_config->nps->event->init->debug->enabled)
        {
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{port}',
                        '{keva}',
                        '{room}'
                    ],
                    [
                        (string) date('c'),
                        (string) $this->_config->nps->server->host,
                        (string) $this->_config->nps->server->port,
                        (float) $this->_kevacoin->getBalance(
                            $this->_config->kevacoin->wallet->account
                        ),
                        (string) print_r(
                            $this->_namespaces,
                            true
                        )
                    ],
                    $this->_config->nps->event->init->debug->template
                ) . PHP_EOL
            );
        }
    }

    public function onOpen(
        \Ratchet\ConnectionInterface $connection
    ) {
        // Init config namespace
        $config = $this->_config->nps;

        // Build captcha
        $captcha = new \Gregwar\Captcha\CaptchaBuilder(
            null,
            new \Gregwar\Captcha\PhraseBuilder(
                $config->captcha->length,
                $config->captcha->chars
            )
        );

        $captcha->setBackgroundColor(
            $config->captcha->background->r,
            $config->captcha->background->g,
            $config->captcha->background->b
        );

        $captcha->build(
            $config->captcha->dimensions->width,
            $config->captcha->dimensions->height
        );

        // Convert captcha image to ASCII response
        $image = new \Ixnode\PhpCliImage\CliImage(
            $captcha->get(),
            $config->captcha->ascii->width
        );

        // Send response
        $connection->send(
            sprintf(
                implode(
                    PHP_EOL,
                    $config->event->open->response
                ) . PHP_EOL . $image->getAsciiString() . PHP_EOL
            )
        );

        // Keep captcha phrase in connection
        $connection->captcha = $captcha->getPhrase();

        // Init connection confirmed
        $connection->confirmed = false;

        // Init connection room
        $connection->namespace = null;

        // Init connection counter
        $connection->count = 0;

        // Init connection message
        $connection->message = '';

        // Debug open event on enabled
        if ($config->event->open->debug->enabled)
        {
            // Print debug from template
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{crid}',
                        '{code}'
                    ],
                    [
                        (string) date('c'),
                        (string) $connection->remoteAddress,
                        (string) $connection->resourceId,
                        (string) $connection->captcha
                    ],
                    $config->event->open->debug->template
                ) . PHP_EOL
            );
        }
    }

    public function onMessage(
        \Ratchet\ConnectionInterface $connection,
        $request
    ) {
        // Filter request
        $request = trim(
            $request
        );

        // Increase connection counter
        $connection->count++;

        // Init config namespace
        $config = $this->_config->nps->event->message;

        // Debug message event on enabled
        if ($config->debug->enabled)
        {
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{crid}',
                        '{code}',
                        '{iter}',
                        '{sent}',
                        '{size}'
                    ],
                    [
                        (string) date('c'),
                        (string) $connection->remoteAddress,
                        (string) $connection->resourceId,
                        (string) $connection->captcha,
                        (string) $connection->count,
                        (string) str_replace('%', '%%', $request),
                        (string) mb_strlen($request)
                    ],
                    $config->debug->template
                ) . PHP_EOL
            );
        }

        // Connection confirmed
        if ($connection->confirmed)
        {
            // Room selected, begin message compose
            if ($connection->namespace)
            {
                // Check message commit by dot
                if ($request == '.')
                {
                    // Check message not empty
                    if (empty(trim($connection->message)))
                    {
                        $connection->send(
                            implode(
                                PHP_EOL,
                                $config->response->submit->failure->empty
                            ) . PHP_EOL
                        );
                    }

                    // Max length already checked on input, begin message save
                    else
                    {
                        // Save massage to KevaCoin blockchain
                        if ($txid = $this->_kevacoin->kevaPut($connection->namespace, time(), $connection->message))
                        {
                            // Return success response
                            $connection->send(
                                str_ireplace(
                                    [
                                        '{name}',
                                        '{txid}'
                                    ],
                                    [
                                        (string) $connection->namespace,
                                        (string) $txid
                                    ],
                                    implode(
                                        PHP_EOL,
                                        $config->response->submit->success
                                    ) . PHP_EOL
                                )
                            );

                            // Print transaction debug info on enabled
                            if ($this->_config->kevacoin->event->put->debug->enabled)
                            {
                                print(
                                    str_ireplace(
                                        [
                                            '{time}',
                                            '{host}',
                                            '{crid}',
                                            '{name}',
                                            '{txid}',
                                            '{keva}'
                                        ],
                                        [
                                            (string) date('c'),
                                            (string) $connection->remoteAddress,
                                            (string) $connection->resourceId,
                                            (string) $connection->namespace,
                                            (string) $txid,
                                            (string) $this->_kevacoin->getBalance(
                                                $this->_config->kevacoin->wallet->account
                                            )
                                        ],
                                        $this->_config->kevacoin->event->put->debug->template
                                    ) . PHP_EOL
                                );
                            }
                        }

                        // Could not receive transaction, something went wrong
                        else
                        {
                            $connection->send(
                                implode(
                                    PHP_EOL,
                                    $config->response->submit->failure->internal
                                ) . PHP_EOL
                            );
                        }
                    }

                    // Close connection at this point
                    $connection->close();
                }

                // Complete message by new line sent
                $connection->message .= $request . PHP_EOL;

                // Check message encoding valid
                if (!mb_check_encoding($connection->message, 'UTF-8'))
                {
                    $connection->send(
                        implode(
                            PHP_EOL,
                            $config->response->submit->failure->encoding
                        ) . PHP_EOL
                    );

                    $connection->close();
                }

                // Check total message length limit allowed by KevaCoin protocol
                if (mb_strlen($connection->message) > 3074)
                {
                    $connection->send(
                        implode(
                            PHP_EOL,
                            $config->response->submit->failure->length
                        ) . PHP_EOL
                    );

                    $connection->close();
                }
            }

            // Room not selected yet and number given match registry
            else if (isset($this->_namespaces[$request]))
            {
                // Update connection namespace
                $connection->namespace = $this->_namespaces[$request]['hash'];

                // Send room selection request
                $connection->send(
                    str_ireplace(
                        [
                            '{room}'
                        ],
                        [
                            str_replace( // filter possible meta mask in the room names
                                '%',
                                '%%',
                                $this->_namespaces[$request]['name']
                            )
                        ],
                        implode(
                            PHP_EOL,
                            $config->response->room->success
                        ) . PHP_EOL
                    )
                );
            }

            // Room number not found in registry
            else
            {
                // Reset connection room anyway
                $connection->namespace = null;

                // Send fail response
                $connection->send(
                    implode(
                        PHP_EOL,
                        $config->response->room->failure
                    ) . PHP_EOL
                );

                // Keep connection alive for new attempt..
            }
        }

        // Captcha confirmation code expected
        else
        {
            // Request match captcha
            if ($request == $connection->captcha)
            {
                // Set connection confirmed
                $connection->confirmed = true;

                // Build room list
                $rooms = [];

                foreach ($this->_namespaces as $number => $namespace)
                {
                    $rooms[] = sprintf(
                        '[%d] %s',
                        $number,
                        $namespace['name']
                    );
                }

                // Send room selection request
                $connection->send(
                    str_ireplace(
                        [
                            '{room:list}'
                        ],
                        [
                            implode(
                                PHP_EOL,
                                $rooms
                            )
                        ],
                        implode(
                            PHP_EOL,
                            $config->response->captcha->success
                        ) . PHP_EOL
                    )
                );
            }

            // Captcha request invalid
            else
            {
                // Reset confirmed status
                $connection->confirmed = false;

                // Send fail response
                $connection->send(
                    implode(
                        PHP_EOL,
                        $config->response->captcha->failure
                    ) . PHP_EOL
                );

                // Drop connection to prevent brute force
                $connection->close();
            }
        }
    }

    public function onClose(
        \Ratchet\ConnectionInterface $connection
    ) {
        if ($this->_config->nps->event->close->debug->enabled)
        {
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{crid}'
                    ],
                    [
                        (string) date('c'),
                        (string) $connection->remoteAddress,
                        (string) $connection->resourceId
                    ],
                    $this->_config->nps->event->close->debug->template
                ) . PHP_EOL
            );
        }
    }

    public function onError(
        \Ratchet\ConnectionInterface $connection,
        \Exception $exception
    ) {
        if ($this->_config->nps->event->close->debug->enabled)
        {
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{crid}',
                        '{info}'
                    ],
                    [
                        (string) date('c'),
                        (string) $connection->remoteAddress,
                        (string) $connection->resourceId,
                        (string) $exception->getMessage()
                    ],
                    $this->_config->nps->event->error->debug->template
                ) . PHP_EOL
            );
        }

        $connection->close();
    }
}