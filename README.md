# npsapp

KevaChat Application for [NPS Protocol](https://nightfall.city/nps/info/specification.txt)

Listen connections on `1900` port and save messages to given `namespace` in [Kevacoin](https://github.com/kevacoin-project) blockchain.

To read messages, use KevaChat [webapp](https://github.com/kevachat/webapp), [geminiapp](https://github.com/kevachat/geminiapp) or [any](https://github.com/kvazar-network/awesome-kevacoin#explorers) KevaCoin Explorer

## Components

* [kevachat/kevacoin-php](https://github.com/kevachat/kevacoin-php) - KevaCoin library for PHP
* [yggverse/nps-php](https://github.com/YGGverse/nps-php) - PHP 8 / Composer Library for NPS Protocol
* [gregwar/captcha](https://github.com/Gregwar/Captcha) - Captcha library to prevent spam abuses
* [ixnode/php-cli-image](https://github.com/ixnode/php-cli-image) - Library converts captcha to ASCII format