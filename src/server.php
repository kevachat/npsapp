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

// Init session code
$code = null;

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
        global $code;

        // Build captcha on enabled
        if ($config->nps->captcha->enabled)
        {
            $captcha = new \Gregwar\Captcha\CaptchaBuilder(
                null,
                new \Gregwar\Captcha\PhraseBuilder(
                    $config->nps->captcha->length,
                    $config->nps->captcha->chars
                )
            );

            $captcha->setBackgroundColor(
                $config->nps->captcha->background->r,
                $config->nps->captcha->background->g,
                $config->nps->captcha->background->b
            );

            $captcha->build(
                $config->nps->captcha->dimensions->width,
                $config->nps->captcha->dimensions->height
            );

            // Set captcha value to the session code
            $code = $captcha->getPhrase();

            // Create ASCII confirmation code
            $image = new \Ixnode\PhpCliImage\CliImage(
                $captcha->get(),
                $config->nps->captcha->ascii->width
            );

            $confirmation = PHP_EOL . $image->getAsciiString() . PHP_EOL;
        }

        else
        {
            $confirmation = null;
            $code = true;
        }

        // Debug request on enabled
        if ($config->nps->action->welcome->debug->enabled)
        {
            // Build connection URL #72811
            $url = sprintf(
                'nex://%s',
                $connect
            );

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
                        (string) parse_url($url, PHP_URL_HOST),
                        (string) parse_url($url, PHP_URL_PORT),
                        (string) is_null($code) ? '[off]' : $code
                    ],
                    $config->nps->action->welcome->debug->template
                ) . PHP_EOL
            );
        }

        return sprintf(
            implode(
                PHP_EOL,
                $config->nps->action->welcome->message
            ) . PHP_EOL . $confirmation
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
        global $code;

        // Debug request on enabled
        if ($config->nps->action->pending->debug->enabled)
        {
            // Build connection URL #72811
            $url = sprintf(
                'nex://%s',
                $connect
            );

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
                        (string) parse_url($url, PHP_URL_HOST),
                        (string) parse_url($url, PHP_URL_PORT),
                        (string) trim($request),
                        (string) is_null($code) ? '[off]' : $code
                    ],
                    $config->nps->action->pending->debug->template
                ) . PHP_EOL
            );
        }

        return is_null($code) || $code == trim($request) ? implode(PHP_EOL, $config->nps->action->pending->message->success) . PHP_EOL
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
        global $code;

        // @TODO save content in blockchain with kevacoin-php

        // Debug request on enabled
        if ($config->nps->action->handler->debug->enabled)
        {
            // Build connection URL #72811
            $url = sprintf(
                'nex://%s',
                $connect
            );

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
                        (string) parse_url($url, PHP_URL_HOST),
                        (string) parse_url($url, PHP_URL_PORT),
                        (string) str_replace('%', '%%', trim($request)),
                        (string) is_null($code) ? '[off]' : $code,
                        (string) mb_strlen($content),
                        (string) PHP_EOL . $content . PHP_EOL,
                    ],
                    $config->nps->action->handler->debug->template
                ) . PHP_EOL
            );
        }

        return $success ? implode(PHP_EOL, $config->nps->action->handler->message->success) . PHP_EOL
                        : implode(PHP_EOL, $config->nps->action->handler->message->failure) . PHP_EOL;
    }
);

// Start server
$server->start();