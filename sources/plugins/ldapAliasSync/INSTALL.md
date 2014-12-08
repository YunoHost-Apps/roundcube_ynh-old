# Install ldapAliasSync in Yunohost

1/ Log into your server with ssh
`ssh admin@example.com`

2/ Download ldapAliasSync in your roundcube plugin directory
```
cd /var/www/roundcube/plugins
git clone https://github.com/opi/ldapAliasSync.git
chown -R www-data:www-data /var/www/roundcube/plugins/ldapAliasSync
```

3/ Edit your RoundCube configuration file
`nano /var/www/roundcube/config/main.inc.php`

Find the line with `$rcmail_config['plugins']` and add `ldapAliasSync`.

4/ Log-out and log-in back to roundcube, and check your identities (in the settings page).
