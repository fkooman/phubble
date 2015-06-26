[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fkooman/phubble/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fkooman/phubble/?branch=master)

# Introduction

# Development
We assume that your web server runs under the `apache` user and your user 
account is called `fkooman` in group `fkooman`.

    $ cd /var/www
    $ sudo mkdir phubble
    $ sudo chown fkooman.fkooman phubble
    $ git clone https://github.com/fkooman/phubble.git
    $ cd phubble
    $ /path/to/composer.phar install
    $ mkdir -p data
    $ sudo chown -R apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/phubble/data(/.*)?'
    $ sudo restorecon -R /var/www/phubble/data
    $ cp config/config.ini.default config/config.ini
    $ cp config/acl.json.example config/acl.json

Now to initialize the database:

    $ sudo -u apache bin/phubble-init-db

# License
Licensed under the GNU Affero General Public License as published by the Free 
Software Foundation, either version 3 of the License, or (at your option) any 
later version.

    https://www.gnu.org/licenses/agpl.html

This roughly means that if you use this software in your service you need to 
make the source code available to the users of your service (if you modify
it). Refer to the license for the exact details.

