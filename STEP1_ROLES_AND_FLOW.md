# QRYPT Step 1: Roles and Attendance Flow

## 1) User Roles

### Admin
- Manages students and visitors.
- Views attendance logs and reports.
- Can update attendance records when needed.
- Manages account/security settings.

### Student
- Uses assigned QR code to scan attendance.
- Can do Time In and Time Out (recommended).
- Does not access admin dashboards.

### Visitor
- Registers basic details (name, purpose, contact).
- Receives a visitor QR code (single-use).
- Scans for Time In and Time Out.

## 2) Attendance Flows

### Student Flow
1. Admin adds student profile (name, section/course, student number).
2. System generates unique student QR code.
3. Student scans at entrance.
4. System checks identity and records attendance.
5. If no open session for today: record Time In.
6. If open session exists: record Time Out.

### Visitor Flow
1. Visitor registers at desk (or pre-registration form).
2. System creates visitor profile and QR code.
3. Visitor scans at entrance.
4. System records Time In.
5. Visitor scans again when leaving.
6. System records Time Out and marks QR as used.
7. Visitor QR becomes invalid for the next visit.

### Admin Flow
1. Admin logs in.
2. Admin views today logs (students + visitors).
3. Admin filters by date/person type/status (inside/already out).
4. Admin can correct invalid entries.
5. Admin can export daily/weekly reports.

## 3) Attendance Rules (Base)
- One open attendance session per person per day.
- First scan of the day = Time In.
- Second scan = Time Out.
- Visitor QR is single-use: valid only for one complete visit (Time In + Time Out).
- Ignore duplicate rapid scans (e.g., within 10 seconds).
- Invalid QR should return a safe error message.

## 4) Minimum Required Data

### Students
- id
- student_no
- full_name
- course_section
- qr_code
- is_active
- created_at

### Visitors
- id
- full_name
- purpose
- contact_no
- qr_code
- is_active
- created_at

### Attendance Logs
- id
- person_type (`student` or `visitor`)
- person_id
- attendance_date
- time_in
- time_out
- status (`in`, `out`)
- created_at

## 5) First Scope (MVP)
- Admin login/logout
- Student masterlist with QR generation
- Visitor registration with QR generation
- Unified scanner page (accepts student/visitor QR with mode toggle)
- Daily attendance table with Time In/Time Out
- Basic date filter for logs

## 6) Out of Scope for MVP (Next Iteration)
- SMS/email notifications
- Face recognition
- Multi-branch/campus support
- Advanced analytics dashboard

## 7) Acceptance Checklist for Step 1
- Roles are defined: Admin, Student, Visitor.
- Student and Visitor scan flows are clear.
- Time In/Time Out behavior is defined.
- Minimum data fields are finalized for schema design.

