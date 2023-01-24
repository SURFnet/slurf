# slurf
SimpleSAMLphp authproc for SURFconext/Mastodon integration

© SURF bv 2023

Licensed under the Apache-2 license, see file `LICENSE`.

Install this as a module under simplesaml `modules/slurf/`.

Call it as an AuthProc Filter as follows:

```php
    'authproc' => [
        40 => 'slurf:Nickname',
    ],
```

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

Please report vulnerabilities via: (not in the public issue tracker)
https://www.surf.nl/en/responsible-disclosure
