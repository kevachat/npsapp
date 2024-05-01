<?php

namespace Kevachat\Npsapp\Server;

use \Ratchet\MessageComponentInterface;

class Ratchet implements MessageComponentInterface
{
    private \Kevachat\Kevacoin\Client $_kevacoin;

    private object $_config;

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

        // Dump event on enabled
        if ($this->_config->nps->event->init->debug->enabled)
        {
            print(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{port}',
                        '{keva}'
                    ],
                    [
                        (string) date('c'),
                        (string) $this->_config->nps->server->host,
                        (string) $this->_config->nps->server->port,
                        (float) $this->_kevacoin->getBalance(
                            $this->_config->kevacoin->wallet->account
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
                    if ($txid = $this->_kevacoin->kevaPut($this->_config->kevacoin->wallet->namespace, time(), $connection->message))
                    {
                        // Return success response
                        $connection->send(
                            str_replace(
                                [
                                    '{name}',
                                    '{txid}'
                                ],
                                [
                                    $this->_config->kevacoin->wallet->namespace,
                                    $txid
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
                                        (string) $this->_config->kevacoin->wallet->namespace,
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

        // Captcha request
        else
        {
            // Request match captcha
            if ($request == $connection->captcha)
            {
                $connection->confirmed = true;

                $connection->send(
                    implode(
                        PHP_EOL,
                        $config->response->captcha->success
                    ) . PHP_EOL
                );
            }

            // Captcha request invalid
            else
            {
                $connection->confirmed = false;

                $connection->send(
                    implode(
                        PHP_EOL,
                        $config->response->captcha->failure
                    ) . PHP_EOL
                );

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