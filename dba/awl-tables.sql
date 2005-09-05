-- Tables needed for AWL Libraries
BEGIN;
ALTER TABLE usr RENAME TO appuser;
ALTER TABLE appuser DROP CONSTRAINT usr_pkey;
ALTER TABLE appuser ADD PRIMARY KEY (username);
COMMIT;
ALTER TABLE appuser DROP CONSTRAINT usr_username_key;
DROP INDEX usr_sk1_unique_username;

BEGIN;
CREATE TABLE organisation (
  org_code SERIAL PRIMARY KEY,
  active BOOL DEFAULT TRUE,
  abbreviation TEXT,
  org_name TEXT
);
CREATE FUNCTION max_organisation() RETURNS INT4 AS 'SELECT max(org_code) FROM organisation' LANGUAGE 'sql';

-- This is the table of users for the system
CREATE TABLE usr (
  user_no SERIAL PRIMARY KEY,
  org_code INT4 REFERENCES organisation ( org_code ),
  active BOOLEAN DEFAULT TRUE,
  validated INT2 DEFAULT 0,
  enabled INT2 DEFAULT 1,
  last_accessed TIMESTAMP,
  linked_user INT4 REFERENCES usr ( user_no ),
  username TEXT NOT NULL UNIQUE,
  password TEXT,
  email TEXT,
  fullname TEXT,
  joined TIMESTAMP DEFAULT current_timestamp,
  last_update TIMESTAMP,
  status CHAR,
  phone TEXT,
  mobile TEXT,
  email_ok BOOL DEFAULT TRUE,
  mail_style CHAR,
  config_data TEXT
);
CREATE FUNCTION max_usr() RETURNS INT4 AS 'SELECT max(user_no) FROM usr' LANGUAGE 'sql';
CREATE UNIQUE INDEX usr_sk1_unique_username ON usr ( lower(username) );

CREATE TABLE usr_setting (
  user_no INT4 REFERENCES usr ( user_no ),
  setting_name TEXT,
  setting_value TEXT,
  PRIMARY KEY ( user_no, setting_name )
);

CREATE FUNCTION get_usr_setting(INT4,TEXT)
    RETURNS TEXT
    AS 'SELECT setting_value FROM usr_setting
            WHERE usr_setting.user_no = $1
            AND usr_setting.setting_name = $2 ' LANGUAGE 'sql';

CREATE TABLE roles (
    role_no SERIAL PRIMARY KEY,
    role_name TEXT
);
CREATE FUNCTION max_roles() RETURNS INT4 AS 'SELECT max(role_no) FROM roles' LANGUAGE 'sql';


CREATE TABLE role_member (
    role_no INT4 REFERENCES roles ( role_no ),
    user_no INT4 REFERENCES usr ( user_no )
);


CREATE TABLE session (
    session_id SERIAL PRIMARY KEY,
    user_no INT4 REFERENCES usr ( user_no ),
    session_start TIMESTAMP DEFAULT current_timestamp,
    session_end TIMESTAMP DEFAULT current_timestamp,
    session_key TEXT,
    session_config TEXT
);
CREATE FUNCTION max_session() RETURNS INT4 AS 'SELECT max(session_id) FROM session' LANGUAGE 'sql';

CREATE TABLE tmp_password (
  user_no INT4 REFERENCES usr ( user_no ),
  password TEXT,
  valid_until TIMESTAMP DEFAULT (current_timestamp + '1 day'::interval)
);
COMMIT;

BEGIN;
GRANT SELECT,INSERT,UPDATE ON
  organisation
  , usr
  , usr_setting
  , roles
  , role_member
  , session
  , tmp_password
  TO general;
GRANT SELECT,UPDATE ON
  organisation_org_code_seq
  , usr_user_no_seq
  , session_session_id_seq
  TO general;

GRANT DELETE ON tmp_password TO general;
COMMIT;