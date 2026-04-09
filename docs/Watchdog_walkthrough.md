# Watchdog Vanguard: Automated Risk Detection

We have finalized the implementation of the high-priority "Vanguard" alert system. Your business is now protected by daily automated audits and immediate risk notifications.

## System Features

### 1. Automated Daily Audits
The synchronization process (`populate-insights`) is now fully automated.
- **Frequency**: Every day.
- **Time**: 09:00 AM.
- **Logic**: Extract data from CAFCA, calculate WIP risks, and refresh the Intelligence Hub.

### 2. Vanguard Immediate Alerts
When the daily audit detects a project exceeding the critical threshold (€20,000 WIP):
- **Immediate Delivery**: A high-impact alert is sent instantly to management.
- **Single-Trigger Execution**: The alert is sent only once when the threshold is first crossed, preventing inbox clutter while ensuring maximum visibility for new risks.
- **Branding**: Includes the official Claesen Verlichting logo and a high-priority "Urgent" design.

### 3. Visual & UI Refinements
- **Logo Integration**: Reconfigured the email headers to use a **Dark Slate (#0f172a)** background, ensuring the `brand-logo-dark.png` asset blends seamlessly without visible borders.
- **Urgency Theme**: Used the corporate red specifically as a high-visibility accent for immediate alerts.

## Summary of Modifications

- **[AnalyticsServiceProvider.php](file:///home/totti/claesen_api_web_oficial/Modules/Analytics/Providers/AnalyticsServiceProvider.php)**: Scheduled the daily ETL process at 09:00 AM.
- **[PopulateProjectInsightsCommand.php](file:///home/totti/claesen_api_web_oficial/Modules/Analytics/Console/Commands/PopulateProjectInsightsCommand.php)**: Integrated the Vanguard trigger logic.
- **[immediate-alert.blade.php](file:///home/totti/claesen_api_web_oficial/Modules/Analytics/resources/views/emails/immediate-alert.blade.php)**: Created the premium high-risk email template.
- **[watchdog-report.blade.php](file:///home/totti/claesen_api_web_oficial/Modules/Analytics/resources/views/emails/watchdog-report.blade.php)**: Updated headers for better logo blending.

## Verification Confirmation

- **Live Test Success**: Your manual run confirmed the system detected a real risk in project **P20250059** and successfully triggered the alert.

> [!NOTE]
> The system is currently monitoring projects with a threshold of **€ 20.000**. You can adjust this value anytime in your `.env` file via `WATCHDOG_IMMEDIATE_THRESHOLD`.
