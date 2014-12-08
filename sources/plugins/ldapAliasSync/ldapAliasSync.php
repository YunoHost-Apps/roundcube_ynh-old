<?php
/*
 * LDAP Alias Sync: Syncronize users' identities (name, email, organization, reply-to, bcc, signature)
 * by querying an LDAP server's aliasses.
 *
 * Based on the 'IdentiTeam' Plugin by André Rodier <andre.rodier@gmail.com>
 * Author: Lukas Mika <lukas.mika@web.de>
 * Licence: GPLv3. (See copying)
 */
class ldapAliasSync extends rcube_plugin {
    public $task = 'login';

    // Internal variables
    private $initialised;
    private $app;
    private $config;
    private $rc_user;

    // mail parameters
    private $mail = array();
    private $search_domain;
    private $replace_domain;
    private $find_domain;
    private $separator;

    // LDAP parameters
    private $ldap;
    private $server;
    private $bind_dn;
    private $bind_pw;
    private $base_dn;
    private $filter;
    private $attr_mail;
    private $attr_name;
    private $attr_org;
    private $attr_reply;
    private $attr_bcc;
    private $attr_sig;
    private $fields;

    function init() {
        try {
            write_log('ldapAliasSync', 'Initialising');
            
            # Load default config, and merge with users' settings
            $this->load_config('config.inc.php');

            $this->app = rcmail::get_instance();
            $this->config = $this->app->config->get('ldapAliasSync');

            # Load LDAP & mail config at once
            $this->ldap = $this->config['ldap'];
            $this->mail = $this->config['mail'];

            # Load LDAP configs
            $this->server       = $this->ldap['server'];
            $this->bind_dn      = $this->ldap['bind_dn'];
            $this->bind_pw      = $this->ldap['bind_pw'];
            $this->base_dn      = $this->ldap['base_dn'];
            $this->filter       = $this->ldap['filter'];
            $this->attr_mail    = $this->ldap['attr_mail'];
            $this->attr_name    = $this->ldap['attr_name'];
            $this->attr_org     = $this->ldap['attr_org'];
            $this->attr_reply   = $this->ldap['attr_reply'];
            $this->attr_bcc     = $this->ldap['attr_bcc'];
            $this->attr_sig     = $this->ldap['attr_sig'];

            # Special features for attrs set above
            $this->attr_mail_ignore = $this->ldap['attr_mail_ignore'];

            # Convert all attribute names to lower case
            $this->attr_mail  = strtolower($this->attr_mail);
            $this->attr_name  = strtolower($this->attr_name);
            $this->attr_org   = strtolower($this->attr_org);
            $this->attr_reply = strtolower($this->attr_reply);
            $this->attr_bcc   = strtolower($this->attr_bcc);
            $this->attr_sig   = strtolower($this->attr_sig);

            $this->fields = array($this->attr_mail, $this->attr_name, $this->attr_org, $this->attr_reply,
                $this->attr_bcc, $this->attr_sig);

            # Load mail configs
            $this->search_domain  = $this->mail['search_domain'];
            $this->replace_domain = $this->mail['replace_domain'];
            $this->find_domain    = $this->mail['find_domain'];
            $this->separator      = $this->mail['dovecot_seperator'];

            # LDAP Connection
            $this->conn = ldap_connect($this->server);

            if ( is_resource($this->conn) ) {
                ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);

                # Bind to LDAP (with account or anonymously)
                if ( $this->bind_dn ){
                    $bound = ldap_bind($this->conn, $this->bind_dn, $this->bind_pw);
                } else {
                    $bound = ldap_bind($this->conn);
                }

                if ( $bound ) {
                    # register hook
                    $this->add_hook('login_after', array($this, 'login_after'));
                    $this->initialised = true;
                } else {
                    $log = sprintf("Bind to server '%s' failed. Con: (%s), Error: (%s)",
                        $this->server,
                        $this->conn,
                        ldap_errno($this->conn));
                    write_log('ldapAliasSync', $log);
                }
            } else {
                $log = sprintf("Connection to the server failed: (Error=%s)", ldap_errno($this->conn));
                write_log('ldapAliasSync', $log);
            }
        } catch ( Exception $exc ) {
            write_log('ldapAliasSync', 'Fail to initialise: '.$exc->getMessage());
        }

        if ( $this->initialised )
            write_log('ldapAliasSync', 'Initialised');

    }

    /**
     * login_after
     * 
     * See http://trac.roundcube.net/wiki/Plugin_Hooks
     * Arguments:
     * - URL parameters (e.g. task, action, etc.)
     * Return values:
     * - task
     * - action 
     * - more URL parameters
     */
    function login_after($args) {
        $this->rc_user = rcmail::get_instance()->user;
        $login = $this->rc_user->get_username('mail');

        try {
            # Get the local part and the domain part of login
            if ( strstr($login, '@') ) {
                $login_parts = explode('@', $login);
                $local_part  = array_shift($login_parts);
                $domain_part = array_shift($login_parts);

                if ( $this->replace_domain && $this->search_domain ) {
                    $domain_part = $this->search_domain;
                }
            } else {
                $local_part = $login;
                if ( $this->search_domain ) {
                    $domain_part = $this->search_domain;
                }
            }

            # Check if dovecot master user is used.
            if ( strstr($login, $this->separator) ) {
                $log = sprintf("Removed dovecot impersonate separator (%s) in the login name", $this->separator);
                write_log('ldapAliasSync', $log);

                $local_part = array_shift(explode($this->separator, $local_part));
            }

            # Set the search email address
            if ( $domain_part ) {
                $login_email = "$local_part@$domain_part";
            } else {
                $domain_part = '';
                $login_email = '';
            }

            $filter = $this->filter;

            # Replace place holders in the LDAP filter with login data
            $ldap_filter = str_replace('%login', $login, $filter);
            $ldap_filter = str_replace('%local', $local_part, $ldap_filter);
            $ldap_filter = str_replace('%domain', $domain_part, $ldap_filter);
            $ldap_filter = str_replace('%email', $login_email, $ldap_filter);

            # Search for LDAP data
            $result = ldap_search($this->conn, $this->base_dn, $ldap_filter, $this->fields);

            if ( $result ) {
                $info = ldap_get_entries($this->conn, $result);

                if ( $info['count'] >= 1 ) {
                    $log = sprintf("Found the user '%s' in the database", $login);
                    write_log('ldapAliasSync', $log);

                    $identities = array();

                    # Collect the identity information
                    for($i=0; $i<$info['count']; $i++) {
                        write_log('ldapAliasSync', $i);
                        $email = null;
                        $name = null;
                        $organization = null;
                        $reply = null;
                        $bcc = null;
                        $signature = null;

                        $ldapID = $info["$i"];
                        $ldap_temp = $ldapID[$this->attr_mail];
                        $email = $ldap_temp[0];
                        if ( $this->attr_name ) {
                            $ldap_temp = $ldapID[$this->attr_name];
                            $name = $ldap_temp[0];
                        }
                        if ( $this->attr_org ) {
                            $ldap_temp = $ldapID[$this->attr_org];
                            $organisation = $ldap_temp[0];
                        }
                        if ( $this->attr_reply ) {
                            $ldap_temp = $ldapID[$this->attr_reply];
                            $reply = $ldap_temp[0];
                        }
                        if ( $this->attr_bcc ) {
                            $ldap_temp = $ldapID[$this->attr_bcc];
                            $bcc = $ldap_temp[0];
                        }
                        if ( $this->attr_sig ) {
                            $ldap_temp = $ldapID[$this->attr_sig];
                            $signature = $ldap_temp[0];
                        }

                        $ldap_temp = $ldapID[$this->attr_mail];
                        for($mi = 0; $mi < $ldap_temp['count']; $mi++) {
                            $email = $ldap_temp[$mi];
                            # If we only found the local part and have a find domain, append it
                            if ( $email && !strstr($email, '@') && $this->find_domain ) $email = "$email@$this->find_domain";

                            # Only collect the identities with valid email addresses
                            if ( strstr($email, '@') ) {
                                # Verify that domain part is not ignored
                                $domain = explode('@', $email)[1];
                                if ( in_array($domain, $this->attr_mail_ignore) ) continue;

                                if ( !$name )         $name         = '';
                                if ( !$organisation ) $organisation = '';
                                if ( !$reply )        $reply        = '';
                                if ( !$bcc )          $bcc          = '';
                                if ( !$signature )    $signature    = '';

                                # If the signature starts with an HTML tag, we mark the signature as HTML
                                if ( preg_match('/^\s*<[a-zA-Z]+/', $signature) ) {
                                    $isHtml = 1;
                                } else {
                                    $isHtml = 0;
                                }

                                $identity = array(
                                    'email'          => $email,
                                    'name'           => $name,
                                    'organization'   => $organisation,
                                    'reply-to'       => $reply,
                                    'bcc'            => $bcc,
                                    'signature'      => $signature,
                                    'html_signature' => $isHtml,
                                );

                                array_push($identities, $identity);
                            } else {
                                $log = sprintf("Domain missing in email address '%s'", $email);
                                write_log('ldapAliasSync', $log);
                            }
                        }
                    }

                    if ( count($identities) > 0 && $db_identities = $this->rc_user->list_identities() ) {
                        # Check which identities not yet contained in the database
                        foreach ( $identities as $identity ) {
                            $in_db = false;

                            foreach ( $db_identities as $db_identity ) {
                                # email is our only comparison parameter
                                if( $db_identity['email'] == $identity['email'] ) {
                                    $in_db = true;
                                    break;
                                }
                            }
                            if( !$in_db ) {
                                $this->rc_user->insert_identity( $identity );
                                $log = "Added identity: ".$identity['email'];
                                write_log('ldapAliasSync', $log);
                            }
                        }

                        # Check which identities are available in database but nut in LDAP and delete those
                        foreach ( $db_identities as $db_identity ) {
                            $in_ldap = false;
                            
                            foreach ( $identities as $identity ) {
                                # email is our only comparison parameter
                                if( $db_identity['email'] == $identity['email'] ) {
                                    $in_ldap = true;
                                    break;
                                }
                            }
                            
                            # If this identity does not exist in LDAP, delete it from database
                            if( !$in_ldap ) {
                                $this->rc_user->delete_identity($db_identity['identity_id']);
                                $log = sprintf("Removed identity: ", $del_id);
                                write_log('ldapAliasSync', $log);
                            }
                        }
                    }
                } else {
                    $log = sprintf("User '%s' not found (pass 2). Filter: %s", $login, $ldap_filter);
                    write_log('ldapAliasSync', $log);
                }
            } else {
                $log = sprintf("User '%s' not found (pass 1). Filter: %s", $login, $ldap_filter);
                write_log('ldapAliasSync', $log);
            }

            ldap_close($this->conn);
        } catch(Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
	}
        return $args;
    }
}
?>
