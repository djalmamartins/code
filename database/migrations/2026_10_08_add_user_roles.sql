-- Apply to databases created before role-based authorization was introduced.
-- Existing accounts safely receive the least-privileged role: user.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'user' AFTER status;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS users_role_index (role);
