CREATE DATABASE IF NOT EXISTS agendamento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agendamento;

CREATE TABLE scheduling_days (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_date DATE NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE scheduling_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduling_day_id BIGINT UNSIGNED NOT NULL,
    service_time TIME NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 6,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_day_time (scheduling_day_id, service_time),
    CONSTRAINT fk_slots_day FOREIGN KEY (scheduling_day_id) REFERENCES scheduling_days(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id BIGINT UNSIGNED NOT NULL,
    subject_type ENUM('id','cpf') NOT NULL,
    subject_value VARCHAR(100) NOT NULL,
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    active_subject_key VARCHAR(120) GENERATED ALWAYS AS (
        CASE WHEN status='active' THEN CONCAT(subject_type, ':', subject_value) ELSE NULL END
    ) STORED,
    booked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_active_subject (active_subject_key),
    INDEX idx_slot_status (slot_id, status),
    INDEX idx_subject (subject_type, subject_value, status),
    CONSTRAINT fk_appointments_slot FOREIGN KEY (slot_id) REFERENCES scheduling_slots(id)
) ENGINE=InnoDB;

INSERT INTO scheduling_days (service_date) VALUES ('2026-08-14'),('2026-08-15'),('2026-08-16');

INSERT INTO scheduling_slots (scheduling_day_id, service_time, capacity)
SELECT d.id, t.service_time, 6
FROM scheduling_days d
CROSS JOIN (
    SELECT '07:00:00' service_time UNION ALL SELECT '08:00:00' UNION ALL SELECT '09:00:00'
    UNION ALL SELECT '10:00:00' UNION ALL SELECT '11:00:00' UNION ALL SELECT '13:00:00'
    UNION ALL SELECT '14:00:00' UNION ALL SELECT '15:00:00' UNION ALL SELECT '16:00:00'
    UNION ALL SELECT '17:00:00' UNION ALL SELECT '18:00:00' UNION ALL SELECT '19:00:00'
) t;
