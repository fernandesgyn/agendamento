-- Importe este arquivo dentro do banco já criado no hPanel/Hostinger.
-- O projeto não usa migrations e este arquivo representa a estrutura completa.

CREATE TABLE people (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cpf CHAR(11) NOT NULL,
    name VARCHAR(150) NOT NULL,
    birth_date DATE NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_people_cpf (cpf),
    INDEX idx_people_active (active),
    INDEX idx_people_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduling_days (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_date DATE NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    active_person_key BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN status='active' THEN person_id ELSE NULL END
    ) STORED,
    booked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_active_person (active_person_key),
    INDEX idx_slot_status (slot_id, status),
    INDEX idx_person_status (person_id, status),
    CONSTRAINT fk_appointments_slot FOREIGN KEY (slot_id) REFERENCES scheduling_slots(id),
    CONSTRAINT fk_appointments_person FOREIGN KEY (person_id) REFERENCES people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
