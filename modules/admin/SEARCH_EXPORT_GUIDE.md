# Admin Section - Search & Export Features Guide

## Overview
The admin section has been enhanced with powerful search and export functionality to reduce scrolling and provide better data management. All records can now be quickly searched and exported to CSV or PDF formats.

## Features Added

### 1. **Active Donors Page** (`active_donors.php`)
- **Search Functionality**: Search donors by name or blood type
  - Located at the top of the Active Donors table
  - Clear button to reset search
  - Results update instantly
- **Export Options**:
  - **CSV Export**: Downloads all filtered donor records as a CSV file
  - **PDF Export**: Downloads all filtered donor records as a formatted text document
  - File naming: `active_donors_YYYY-MM-DD_HH-MM-SS.csv/pdf`

**Use Cases:**
- Generate reports for supervisor review
- Create backup files of donor information
- Share data with other departments
- Track donor statistics over time

---

### 2. **Recipients Management Page** (`recipients.php`)
- **Search Functionality**: Search recipients by username or hospital name
  - Located at the top of the Recipients table
  - Status filter (All, Active, Inactive)
  - Clear button to reset search
  - Results update instantly
- **Status Management**: Activate/Deactivate recipients
  - Toggle recipient status with one click
  - Audit logging for all status changes
- **Statistics Dashboard**: Overview of total, active, and inactive recipients
- **Export Options**:
  - **CSV Export**: Downloads all filtered recipient records as a CSV file
  - **PDF Export**: Downloads all filtered recipient records as a formatted text document
  - File naming: `recipients_YYYY-MM-DD_HH-MM-SS.csv/pdf`

**Use Cases:**
- Monitor recipient registration trends
- Manage recipient access and status
- Generate compliance reports
- Track hospital partnerships

---

### 3. **Inventory Management Page** (`inventory.php`)
- **Filter Functionality**: Filter blood inventory by blood type
  - Dropdown filter showing all blood types (A+, A-, B+, B-, AB+, AB-, O+, O-)
  - Quick access to specific blood type inventory
- **Export Options**:
  - **CSV Export**: Downloads complete inventory snapshot with units and last update time
  - **PDF Export**: Downloads inventory report with total units calculation
  - Includes total units summary in exports
  - File naming: `blood_inventory_YYYY-MM-DD_HH-MM-SS.csv/pdf`

**Features:**
- Filters work with export (exports only filtered results)
- Total units summary automatically calculated in CSV
- Timestamps included for audit purposes

---

### 3. **Dashboard - Blood Requests Section** (`dashboard.php`)
- **Advanced Search**: Search blood requests by:
  - Recipient name
  - Blood type
  - Hospital name
- **Status Filter**: View requests by status
  - PENDING: Awaiting approval/rejection
  - APPROVED: Already processed
  - REJECTED: Denied requests
  - ALL: View all requests regardless of status
- **Export Options**:
  - **CSV Export**: Detailed request records with recipient info, hospital, and status
  - **PDF Export**: Formatted report of blood requests with totals
  - File naming: `blood_requests_YYYY-MM-DD_HH-MM-SS.csv/pdf`

**Advanced Filtering:**
- Combine search and status filters for precise results
- Export respects both filters (exports only matching results)
- Approval/rejection buttons still available for pending requests

---

## How to Use

### Search & Filter
1. Locate the search/filter box on the relevant admin page
2. Enter search term (for donors/requests) or select filter option (for blood types)
3. Click "Search" or "Filter" button
4. Results update instantly
5. Click "Clear" button to reset search (donors page only)

### Export Data
1. (Optional) Apply search/filter to narrow down results
2. Click **CSV** button for spreadsheet format or **PDF** button for document format
3. Browser automatically downloads the file
4. File is saved with timestamp for easy identification

### Example File Names
- `active_donors_2026-05-12_14-30-45.csv`
- `blood_inventory_2026-05-12_14-31-20.pdf`
- `blood_requests_2026-05-12_14-32-15.csv`

---

## File Formats

### CSV Format
- **Best for**: Spreadsheet software (Excel, Google Sheets), data analysis
- **Contents**: Headers + data rows
- **Compatibility**: All spreadsheet applications
- **Includes**:
  - Column headers
  - All searched/filtered records
  - Summary row (for inventory)

### PDF Format
- **Best for**: Printing, archiving, sharing formatted reports
- **Contents**: Professional formatted report
- **Compatibility**: All PDF readers
- **Includes**:
  - Report title
  - Generated timestamp
  - Column headers and data
  - Summary statistics

---

## Technical Details

### Export Files Generated
1. **export_donors.php**
   - Exports: Active donors only
   - Searchable by: Username, Blood Group
   - Audit logged as: `DONOR_EXPORT_CSV` or `DONOR_EXPORT_PDF`

2. **export_recipients.php**
   - Exports: All recipients (filtered by status)
   - Searchable by: Username, Hospital Name
   - Audit logged as: `RECIPIENT_EXPORT_CSV` or `RECIPIENT_EXPORT_PDF`

3. **export_inventory.php**
   - Exports: Current blood inventory
   - Searchable by: Blood Type
   - Audit logged as: `INVENTORY_EXPORT_CSV` or `INVENTORY_EXPORT_PDF`

4. **export_requests.php**
   - Exports: Blood requests (filtered by status)
   - Searchable by: Recipient name, Blood type, Hospital
   - Audit logged as: `REQUEST_EXPORT_CSV` or `REQUEST_EXPORT_PDF`

### Security Features
- All exports require ADMIN role authentication
- HTTP 403 error returned if non-admin attempts access
- All export actions are logged in audit_log table
- Includes export timestamp for compliance

### Audit Logging
All exports are automatically logged with:
- User ID of person performing export
- Export format (CSV or PDF)
- Record count in the export
- Current timestamp

---

## Tips & Best Practices

### Recipients Management
✓ Use status filter to manage active/inactive recipients
✓ Search by hospital to monitor partnerships
✓ Export recipient lists for compliance audits
✓ Use activate/deactivate for access control
✓ Keep CSV records for regulatory requirements

### Donors List
✓ Use search to find specific donors quickly
✓ Export before contacting donors for campaigns
✓ Keep CSV files as backup records
✓ Share PDF reports with supervisors

### Inventory Management
✓ Use blood type filter to check specific types
✓ Export daily/weekly reports for management
✓ Monitor low-stock warnings
✓ Use PDF for printing physical reports

### Blood Requests
✓ Use status filter to focus on pending requests
✓ Search by hospital for quick batch approvals
✓ Export rejected requests for follow-up analysis
✓ Keep CSV records for compliance documentation

---

## Troubleshooting

**Q: Export file is not downloading**
A: Check browser popup/download settings. Files may be blocked by security settings.

**Q: Exported file is empty**
A: Apply search filters that have results. Empty searches with no records will show empty files.

**Q: Can't see export buttons**
A: Ensure you're logged in as ADMIN. Non-admin users cannot access export features.

**Q: Search not finding results**
A: Search is case-sensitive in some fields. Try variations of the search term.

---

## Related Documentation
- Security Policy: See [README_TLS.md](../../README_TLS.md)
- Database Schema: See [db/database.sql](../../db/database.sql)
- Audit Logging: See [core/AuditLog.php](../../core/AuditLog.php)
