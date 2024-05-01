# npsapp

KevaChat Server for [NPS Protocol](https://nightfall.city/nps/info/specification.txt)

Listen connections on `1915` port and save messages to given `namespace` in [KevaCoin](https://github.com/kevacoin-project) blockchain

![kevachat/npsapp](https://github.com/kevachat/npsapp/assets/108541346/36f8c2c2-197b-4f0f-9596-e92528293dfc)

To read messages, use KevaChat [webapp](https://github.com/kevachat/webapp), [geminiapp](https://github.com/kevachat/geminiapp) or any [KevaCoin explorer](https://github.com/kvazar-network/awesome-kevacoin#explorers)!

## Components

* [kevachat/kevacoin-php](https://github.com/kevachat/kevacoin-php) - KevaCoin library for PHP 8
* [cboden/ratchet](https://github.com/ratchetphp/Ratchet) - Asynchronous Socket server
* [gregwar/captcha](https://github.com/Gregwar/Captcha) - Captcha library to prevent spam abuse
* [ixnode/php-cli-image](https://github.com/ixnode/php-cli-image) - Library converts captcha to ASCII format

## Install

* `git clone https://github.com/kevachat/npsapp.git`
* `cd npsapp`
* `composer update`

## Setup

* `cd npsapp`
* `cp config/example.json config/name.json` - edit connection and provide room namespace

## Launch

* `php src/app.php name.json` - where `name.json` argument is any config, placed at `config` folder

### Autostart

Launch server as the `systemd` service

You can create as many servers as wanted by providing separated config for each instance!

Following example require `npsapp` installed into the home directory of `npsapp` user (`useradd -m npsapp`)

1. `sudo nano /etc/systemd/system/npsapp.service` - create new service:

``` npsapp.service
[Unit]
After=network.target

[Service]
Type=simple
User=npsapp
Group=npsapp
ExecStart=/usr/bin/php /home/npsapp/npsapp/src/app.php name.json
StandardOutput=file:/home/npsapp/debug.log
StandardError=file:/home/npsapp/error.log
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

2. `sudo systemctl daemon-reload` - reload systemd configuration
3. `sudo systemctl enable npsapp` - enable `npsapp` service on system startup
4. `sudo systemctl start npsapp` - start `npsapp` server

## Clients

* `nc 127.0.0.1 1915` - IPv4 only
* `ncat 127.0.0.1 1915` - IPv4/6, UTF-8 support
* `telnet 127.0.0.1 1915` - IPv4/6, may cause issues with cyrillic messages