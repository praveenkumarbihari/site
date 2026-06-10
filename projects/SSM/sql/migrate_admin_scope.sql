-- Per-admin data isolation (safe to re-run)

ALTER TABLE orders ADD COLUMN IF NOT EXISTS admin_id INTEGER REFERENCES admins(id) ON DELETE CASCADE;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS admin_id INTEGER REFERENCES admins(id) ON DELETE CASCADE;

UPDATE orders SET admin_id = sub.id
FROM (SELECT id FROM admins ORDER BY id LIMIT 1) sub
WHERE orders.admin_id IS NULL AND sub.id IS NOT NULL;

UPDATE support_tickets SET admin_id = sub.id
FROM (SELECT id FROM admins ORDER BY id LIMIT 1) sub
WHERE support_tickets.admin_id IS NULL AND sub.id IS NOT NULL;

DELETE FROM support_tickets WHERE admin_id IS NULL;
DELETE FROM orders WHERE admin_id IS NULL;

ALTER TABLE orders ALTER COLUMN admin_id SET NOT NULL;
ALTER TABLE support_tickets ALTER COLUMN admin_id SET NOT NULL;

ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_id_key;
ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_admin_order_unique;
ALTER TABLE orders ADD CONSTRAINT orders_admin_order_unique UNIQUE (admin_id, order_id);

ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS support_tickets_ticket_id_key;
ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS tickets_admin_ticket_unique;
ALTER TABLE support_tickets ADD CONSTRAINT tickets_admin_ticket_unique UNIQUE (admin_id, ticket_id);

CREATE INDEX IF NOT EXISTS idx_orders_admin ON orders(admin_id);
CREATE INDEX IF NOT EXISTS idx_tickets_admin ON support_tickets(admin_id);
