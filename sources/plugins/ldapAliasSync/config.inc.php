<?php
/*
 * Default configuration settings for ldapAliasSync roundcube plugin
 * Copy this file in config.inc.php, and override the values you need.
*/

$rcmail_config['ldapAliasSync'] = array(
    // Mail parameters
    'mail' => array(
        # Domain to use for LDAP searches (optional)
        # If no login name is given (or 'replace_domain' is true),
        # the domain part for the LDAP filter is set to this value
        'search_domain'     => 'localhost',

        # Replace domain part for LDAP searches
        # This parameter can be used in order to override the login domain part with
        # the value maintained in 'search_domain'
        'replace_domain'    => false,

        # Domain to add to found local parts (asdf --> asdf@example.com) (optional)
        # If the returned value ('mail_attr') does only contain the local part of an email address,
        # this domain will be used as the domain part.
        # This may only be empty, if all identities to be found contain domain parts
        # in their email addresses as all identities without a domain part in the email
        # address will not be returned!
        'find_domain'       => '',

        # Dovecot master user seperator (optional)
        # If you use the dovecot impersonation feature, this seperator will be used
        # in order to determine the actual login name.
        # Set it to the same character if using this feature, otherwise you can also
        # leave it empty.
        'dovecot_seperator' => '*',
    ),

    // LDAP parameters
    'ldap' => array(
        # LDAP server address (required)
        'server'     => 'ldap://localhost',

        # LDAP Bind DN (requried, if no anonymous read rights are set for the accounts)
        // 'bind_dn'    => 'cn=mail,ou=services,dc=example,dc=com',
        'bind_dn'    => '',


        # Bind password (required, if the bind DN needs to authenticate)
        // 'bind_pw'    => 'secret',
        'bind_pw'    => '',

        # LDAP search base (required)
        // 'base_dn'    => 'ou=users,dc=example,dc=com',
        'base_dn'    => 'ou=users,dc=yunohost,dc=org',

        # LDAP search filter (required)
        # This open filter possibility is the heart of the LDAP search.
        # - Use '%login' as a place holder for the login name
        # - Use '%local' as a place holder for the login name local part
        # - Use '%domain' as a place holder for the login name domain part (/'search_domain', if not given or replaced)
        # - Use '%email' as a place holder for the email address ('%local'@'%domain')
        # However, remember to search for the original entry, too (e.g. 'uid=%1$s'), as this is an identity as well!
        // 'filter'     => '(|(uid=%local)(aliasedObjectName=uid=%local,ou=users,dc=example,dc=com))',
        // 'filter'     => '(|(uid=%local)(aliasedObjectName=uid=%local))',
        'filter'     => '(uid=%local)',

        # LDAP email attribute (required)
        # If only the local part is returned, the 'find_domain' is appended (e.g. uid=asdf --> asdf@example.com).
        # If no domain part is returned and no 'find_domain' is given, the identity will not be fetched!
        // 'attr_mail'  => 'uid',
        'attr_mail'  => 'mail',

        # LDAP name attribute (optional)
        'attr_name'  => 'cn',

        # LDAP organization attribute (optional)
        'attr_org'   => 'o',

        # LDAP reply-to attribute (optional)
        'attr_reply' => '',

        # LDAP bcc (blind carbon copy) attribute (optional)
        'attr_bcc'   => '',

        # LDAP signature attribute (optional)
        'attr_sig'   => '',

        # Domain parts to ignore in attr_mail (optional)
        'attr_mail_ignore' => array(),
    ),
);
?>
