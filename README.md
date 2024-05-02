# npsapp

KevaChat Server for [NPS Protocol](https://nightfall.city/nps/info/specification.txt)

Listen connections on `1915` port and save messages to given `namespace` in [KevaCoin](https://github.com/kevacoin-project) blockchain

![kevachat/npsapp](https://github.com/kevachat/npsapp/assets/108541346/936191f7-7ea7-44bd-bcd9-085f2a7f0a7b)

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

## Proxy

Like [NEX Protocol](https://nightfall.city/nex/info/specification.txt), NPS data could be simply passed using any proxy server that support TCP forwarding

### Nginx

* `sudo nano /etc/nginx/nginx.conf`

``` /etc/nginx/nginx.conf
stream {
        server {
                listen 1915;
                proxy_pass 127.0.0.1:1915;
        }
}
```

* `sudo systemctl restart nginx`

## Clients

* `nc 127.0.0.1 1915` - IPv4 only, install `netcat-openbsd` to add IPv6 support
* `ncat 127.0.0.1 1915`
* `telnet 127.0.0.1 1915`

## Servers

* Instance by [YGGverse](https://github.com/YGGverse)
  * `[201:23b4:991a:634d:8359:4521:5576:15b7]:1915` - [Yggdrasil](https://github.com/yggdrasil-network) network
    * `kevachat.ygg:1915` - [Alfis DNS](https://github.com/Revertron/Alfis) alias
    * `kevachat.duckdns.org:1915` - Internet proxy (IPv4)
