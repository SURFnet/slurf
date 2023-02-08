# slurf
SimpleSAMLphp authproc for SURFconext/Mastodon integration

This is a SimpleSAMLphp module that provides an interface before Mastodon.
Its intended use is being configured in a SimpleSAMLphp installation (proxy)
that Mastodon authenticates to. This SimpleSAMLphp can then delegate the
real authentication to various IdPs.

The module performs two functions:
* Nickname selection. THe user's nickname/accountname is not usually available
  in a SAML attribute in the IdP. When the user logs in, they are asked to pick
  a nickname first. When submitted, the nickname attribute is set to this value
  and Mastodon will pick this up. On a return visit, this is skipped and the
  stored nickname for this account is silently provided to Mastodon.
* Group account selection. When an isMemberOf attribute is passed with group
  names, the module can look up which accounts this group of the user has access
  to and present the user with a choice of identities.

# Installation

The module requires a working SimpleSAMLphp >= 2.0 installation. Mastodon
needs to use this installation as an IdP.

Install this as a module under simplesaml `modules/slurf/`.

Call it as an AuthProc Filter as follows, e.g. in the saml20-sp-remote metadata
of the mastodon instance:

```php
    'authproc' => [
        40 => [
            'class' => 'slurf:Nickname',
            'assetsbase' => '/system/',
        ],
    ],
```

You can set assetsbase to the base URL where your user avatars are to be found,
if using the accountchooser functionality.

Configure the Mastodon database in SSP's `config.php` as the `database.*` settings.

Needs database table in the mastodon database:

```sql
CREATE TYPE idtype AS ENUM ('person', 'group');

CREATE TABLE saml2nick (
    nickname character varying(256) DEFAULT ''::character varying NOT NULL,
    idtype public.idtype DEFAULT 'person'::public.idtype NOT NULL,
    saml_id character varying(256) DEFAULT ''::character varying NOT NULL,
    homeorg character varying(256) DEFAULT ''::character varying NOT NULL
);

CREATE INDEX index_saml2nick_on_id ON saml2nick USING btree (saml_id, idtype);

CREATE UNIQUE INDEX index_saml2nick_on_nickname ON saml2nick USING btree (lower((nickname)::text));
```

# Used attributes

By default, the module uses the following attributes:

* To send the nickname to Mastodon: eduPersonNickname
* The identifier from the IdP: persistent NameID
* The home org of the user: schacHomeOrganization
* Group memberships: isMemberOf

The module only looks in its own saml2nick table for available nicknames,
i.e. it assumtes that is the single source of truth that a nickname is
available or taken. 

# License, contact

© SURF bv 2023

Licensed under the Apache-2 license, see file `LICENSE`.

Please report vulnerabilities via: (not in the public issue tracker)
https://www.surf.nl/en/responsible-disclosure
