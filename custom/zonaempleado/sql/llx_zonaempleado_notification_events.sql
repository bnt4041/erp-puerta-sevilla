-- ===================================================================
-- Copyright (C) 2025 Zona Empleado Dev
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <https://www.gnu.org/licenses/>.
--
-- This file registers ZonaEmpleado notification events
-- ===================================================================

-- Register ZonaEmpleado notification events in c_action_trigger table
INSERT INTO llx_c_action_trigger (elementtype, code, label, description, rang) VALUES
('user', 'ZONAEMPLEADO_USER_LOGIN', 'Employee zone: User login', 'Triggered when a user logs into the employee zone portal', 1),
('user', 'ZONAEMPLEADO_USER_LOGOUT', 'Employee zone: User logout', 'Triggered when a user logs out from the employee zone portal', 2),
('user', 'ZONAEMPLEADO_USER_REGISTRATION', 'Employee zone: User registration', 'Triggered when a new user is registered in the system', 3),
('user', 'ZONAEMPLEADO_PROFILE_UPDATED', 'Employee zone: Profile updated', 'Triggered when a user profile is updated', 4),
('document', 'ZONAEMPLEADO_DOCUMENT_SHARED', 'Employee zone: Document shared', 'Triggered when a document is shared with employees', 5),
('announcement', 'ZONAEMPLEADO_ANNOUNCEMENT_CREATED', 'Employee zone: Announcement created', 'Triggered when a new announcement is created', 6),
('announcement', 'ZONAEMPLEADO_ANNOUNCEMENT_UPDATED', 'Employee zone: Announcement updated', 'Triggered when an announcement is updated', 7),
('holiday', 'ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED', 'Employee zone: Holiday request submitted', 'Triggered when an employee submits a holiday request', 8),
('holiday', 'ZONAEMPLEADO_HOLIDAY_REQUEST_APPROVED', 'Employee zone: Holiday request approved', 'Triggered when a holiday request is approved', 9),
('holiday', 'ZONAEMPLEADO_HOLIDAY_REQUEST_REJECTED', 'Employee zone: Holiday request rejected', 'Triggered when a holiday request is rejected', 10),
('payslip', 'ZONAEMPLEADO_PAYSLIP_PUBLISHED', 'Employee zone: Payslip published', 'Triggered when a payslip is published for employees', 11),
('message', 'ZONAEMPLEADO_MESSAGE_RECEIVED', 'Employee zone: Message received', 'Triggered when an employee receives a message', 12),
('schedule', 'ZONAEMPLEADO_SCHEDULE_MODIFIED', 'Employee zone: Schedule modified', 'Triggered when an employee schedule is modified', 13);
