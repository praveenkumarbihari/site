-- SSM - Swiggy Customer Support Analytics Schema (MySQL)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS support_tickets;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     VARCHAR(50)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_employee (employee_id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    admin_id            INT NOT NULL,
    order_id            VARCHAR(50)  NOT NULL,
    customer_name       VARCHAR(100) NOT NULL,
    customer_phone      VARCHAR(20)  NULL,
    customer_email      VARCHAR(150) NULL,
    restaurant          VARCHAR(150) NULL,
    restaurant_id       VARCHAR(50)  NULL,
    delivery_partner    VARCHAR(100) NULL,
    status              VARCHAR(50)  DEFAULT 'Pending',
    eta                 VARCHAR(50)  NULL,
    eta_shown_min       INT          NULL,
    actual_delivery_min INT          NULL,
    delay_min           INT          NULL,
    amount              DECIMAL(10,2) DEFAULT 0,
    order_date          DATE         NULL,
    is_peak_hour        TINYINT(1)   DEFAULT 0,
    weather             VARCHAR(50)  NULL,
    is_first_order      TINYINT(1)   DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY orders_admin_order_unique (admin_id, order_id),
    KEY idx_orders_admin (admin_id),
    KEY idx_orders_status (status),
    KEY idx_orders_restaurant (restaurant_id),
    KEY idx_orders_delay (delay_min),
    CONSTRAINT fk_orders_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_tickets (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    admin_id            INT NOT NULL,
    ticket_id           VARCHAR(50)  NOT NULL,
    customer_name       VARCHAR(100) NOT NULL,
    order_id            VARCHAR(50)  NULL,
    category            VARCHAR(100) NULL,
    priority            VARCHAR(20)  DEFAULT 'Medium',
    status              VARCHAR(50)  DEFAULT 'Open',
    agent               VARCHAR(100) NULL,
    csat_score          DECIMAL(3,1) NULL,
    compensation_amount DECIMAL(10,2) DEFAULT 0,
    support_channel     VARCHAR(20)  DEFAULT 'Chat',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at         TIMESTAMP NULL DEFAULT NULL,
    refund_completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY tickets_admin_ticket_unique (admin_id, ticket_id),
    KEY idx_tickets_admin (admin_id),
    KEY idx_tickets_status (status),
    KEY idx_tickets_category (category),
    KEY idx_tickets_order (order_id),
    CONSTRAINT fk_tickets_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
