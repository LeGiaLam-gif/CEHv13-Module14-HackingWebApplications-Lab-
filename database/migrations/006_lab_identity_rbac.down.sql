ALTER TABLE customers
    DROP FOREIGN KEY fk_customers_user,
    DROP INDEX uq_customers_user,
    DROP COLUMN user_id;

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
