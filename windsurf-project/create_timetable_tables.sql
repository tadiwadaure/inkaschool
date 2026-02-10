-- Timetable Management Tables

-- Teaching Timetable Table
CREATE TABLE IF NOT EXISTS teaching_timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    academic_year VARCHAR(20) NOT NULL,
    term ENUM('Term 1', 'Term 2', 'Term 3') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_timetable_slot (class_id, day_of_week, start_time, end_time, academic_year, term)
);

-- Student Timetable Table (Generated from Teaching Timetable)
CREATE TABLE IF NOT EXISTS student_timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teaching_timetable_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teaching_timetable_id) REFERENCES teaching_timetable(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_timetable (student_id, teaching_timetable_id)
);

-- Student Subject Enrollment Table
CREATE TABLE IF NOT EXISTS student_subject_enrollment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term ENUM('Term 1', 'Term 2', 'Term 3') NOT NULL,
    status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, subject_id, academic_year, term)
);

-- Indexes for better performance
CREATE INDEX idx_teaching_timetable_teacher ON teaching_timetable(teacher_id);
CREATE INDEX idx_teaching_timetable_class ON teaching_timetable(class_id);
CREATE INDEX idx_teaching_timetable_day_time ON teaching_timetable(day_of_week, start_time);
CREATE INDEX idx_student_timetable_student ON student_timetable(student_id);
CREATE INDEX idx_student_enrollment_student ON student_subject_enrollment(student_id);
CREATE INDEX idx_student_enrollment_subject ON student_subject_enrollment(subject_id);
