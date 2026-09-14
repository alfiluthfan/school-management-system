# Enum Map

| Model / Pivot | Column | Enum |
|---|---|---|
| Student | gender | Common\Gender |
| Student | status | Academic\StudentStatus |
| Teacher | gender | Common\Gender |
| Teacher | employment_status | Academic\TeacherEmploymentStatus |
| Teacher | status | Academic\TeacherStatus |
| ParentStudent | relationship | Academic\ParentRelationship |
| SchoolClass | status | Academic\SchoolClassStatus |
| StudentClassEnrollment | status | Academic\EnrollmentStatus |
| AttendanceSchedule | attendance_type | Attendance\AttendanceType |
| StudentAttendance | status | Attendance\AttendanceStatus |
| StudentAttendance | source | Attendance\AttendanceSource |
| TeacherAttendance | status | Attendance\AttendanceStatus |
| TeacherAttendance | source | Attendance\AttendanceSource |
| TeacherLeave | leave_type | Attendance\TeacherLeaveType |
| TeacherLeave | status | Attendance\TeacherLeaveStatus |
| SavingAccount | status | Finance\SavingAccountStatus |
| SavingTransaction | transaction_type | Finance\SavingTransactionType |
| SavingTransaction | status | Finance\SavingTransactionStatus |
| SppBill | status | Finance\SppBillStatus |
| SppPayment | payment_method | Finance\PaymentMethod |
| SppPayment | status | Finance\SppPaymentStatus |
| Announcement | target_scope | Communication\AnnouncementTargetScope |
| Announcement | status | Communication\AnnouncementStatus |
| NotificationLog | type | Communication\NotificationType |
| NotificationLog | channel | Communication\NotificationChannel |
| NotificationLog | status | Communication\NotificationStatus |
| Approval | module | System\ApprovalModule |
| Approval | action | System\ApprovalAction |
| Approval | status | System\ApprovalStatus |
