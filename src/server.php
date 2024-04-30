<?php

// Load dependencies
require_once __DIR__ .
             DIRECTORY_SEPARATOR . '..'.
             DIRECTORY_SEPARATOR . 'vendor' .
             DIRECTORY_SEPARATOR . 'autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ .
        DIRECTORY_SEPARATOR . '..'.
        DIRECTORY_SEPARATOR . 'config.json'
    )
);

// Init session
$session = [];

// Init server
$server = new \Yggverse\Nps\Server(
    $config->nps->server->host,
    $config->nps->server->port,
    $config->nps->server->size,
    $config->nps->server->line
);

// Init welcome function
$server->setWelcome(
    function (
        string $connect
    ): ?string
    {
        global $config;
        global $session;

        // Cleanup expired sessions
        foreach ($session as $key => $value)
        {
            if ($value['time'] + $config->nps->session->timeout < time())
            {
                unset(
                    $session[$key]
                );
            }
        }

        // Build connection URL #72811
        $url = sprintf(
            'nex://%s',
            $connect
        );

        // Init new session
        $session[$connect] =
        [
            'time' => time(),
            'host' => parse_url(
                $url,
                PHP_URL_HOST
            ),
            'port' => parse_url(
                $url,
                PHP_URL_PORT
            ),
            'code' => null
        ];

        // Build captcha
        $captcha = new \Gregwar\Captcha\CaptchaBuilder(
            null,
            new \Gregwar\Captcha\PhraseBuilder(
                $config->nps->session->captcha->length,
                $config->nps->session->captcha->chars
            )
        );

        $captcha->setBackgroundColor(
            $config->nps->session->captcha->background->r,
            $config->nps->session->captcha->background->g,
            $config->nps->session->captcha->background->b
        );

        $captcha->build(
            $config->nps->session->captcha->dimensions->width,
            $config->nps->session->captcha->dimensions->height
        );

        // Set captcha value to the session code
        $session[$connect]['code'] = $captcha->getPhrase();

        // Create ASCII confirmation code
        $image = new \Ixnode\PhpCliImage\CliImage(
            $captcha->get(),
            $config->nps->session->captcha->ascii->width
        );

        // Debug request on enabled
        if ($config->nps->action->welcome->debug->enabled)
        {
            // Print debug from template
            printf(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{port}',
                        '{code}'
                    ],
                    [
                        (string) date('c'),
                        (string) $session[$connect]['host'],
                        (string) $session[$connect]['port'],
                        (string) $session[$connect]['code']
                    ],
                    $config->nps->action->welcome->debug->template
                ) . PHP_EOL
            );
        }

        return sprintf(
            implode(
                PHP_EOL,
                $config->nps->action->welcome->message
            ) . PHP_EOL . $image->getAsciiString() . PHP_EOL
        );
    }
);

// Init pending function
$server->setPending(
    function (
        string $request,
        string $connect
    ): ?string
    {
        global $config;
        global $session;

        // Filter request
        $request = trim(
            $request
        );

        // Debug request on enabled
        if ($config->nps->action->pending->debug->enabled)
        {
            // Print debug from template
            printf(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{port}',
                        '{sent}',
                        '{code}'
                    ],
                    [
                        (string) date('c'),
                        (string) $session[$connect]['host'],
                        (string) $session[$connect]['port'],
                        (string) $request,
                        (string) $session[$connect]['code']
                    ],
                    $config->nps->action->pending->debug->template
                ) . PHP_EOL
            );
        }

        return $session[$connect]['code'] == $request ? implode(PHP_EOL, $config->nps->action->pending->message->success) . PHP_EOL
                                                      : implode(PHP_EOL, $config->nps->action->pending->message->failure) . PHP_EOL;
    }
);

// Init handler function
$server->setHandler(
    function (
          bool $success,
        string $content,
        string $request,
        string $connect
    ): ?string
    {
        global $config;
        global $session;

        // Filter request
        $request = trim(
            $request
        );

        // Filter content
        $content = trim(
            $content
        );

        // @TODO save content in blockchain with kevacoin-php

        // Debug request on enabled
        if ($config->nps->action->handler->debug->enabled)
        {
            // Print debug from template
            printf(
                str_ireplace(
                    [
                        '{time}',
                        '{host}',
                        '{port}',
                        '{sent}',
                        '{code}',
                        '{size}',
                        '{data}'
                    ],
                    [
                        (string) date('c'),
                        (string) $session[$connect]['host'],
                        (string) $session[$connect]['port'],
                        (string) str_replace('%', '%%', $request),
                        (string) $session[$connect]['code'],
                        (string) mb_strlen($content),
                        (string) PHP_EOL . $content,
                    ],
                    $config->nps->action->handler->debug->template
                ) . PHP_EOL
            );
        }

        return $session[$connect]['code'] == $request ? implode(PHP_EOL, $config->nps->action->handler->message->success) . PHP_EOL
                                                      : implode(PHP_EOL, $config->nps->action->handler->message->failure) . PHP_EOL;
    }
);

// Start server
$server->start();