USE agendamento;

CREATE TABLE IF NOT EXISTS authorized_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_type ENUM('id','cpf') NOT NULL,
    subject_value VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_authorized_subject (subject_type, subject_value),
    INDEX idx_authorized_active (active)
) ENGINE=InnoDB;
