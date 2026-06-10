-- Analytics columns migration (safe to re-run)
ALTER TABLE orders ADD COLUMN IF NOT EXISTS restaurant_id VARCHAR(50);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS eta_shown_min INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS actual_delivery_min INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delay_min INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_peak_hour BOOLEAN DEFAULT FALSE;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS weather VARCHAR(50);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_first_order BOOLEAN DEFAULT FALSE;

ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS compensation_amount DECIMAL(10,2) DEFAULT 0;
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS support_channel VARCHAR(20) DEFAULT 'Chat';
ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS refund_completed_at TIMESTAMP;

UPDATE orders SET delay_min = actual_delivery_min - eta_shown_min
WHERE delay_min IS NULL AND actual_delivery_min IS NOT NULL AND eta_shown_min IS NOT NULL;

UPDATE orders SET restaurant_id = restaurant WHERE restaurant_id IS NULL AND restaurant IS NOT NULL;
