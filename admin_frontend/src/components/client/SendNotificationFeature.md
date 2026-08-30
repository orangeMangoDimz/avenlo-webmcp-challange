# Send Notification Feature

## Overview

The Send Notification feature allows administrators to send notifications to clients through the system. This is a frontend-only implementation that provides a complete UI/UX for notification management.

## Components

### SendNotificationModal.vue

Located in: `admin_frontend/src/components/client/SendNotificationModal.vue`

A comprehensive modal component for sending notifications to individual clients.

## Features

### 1. **Recipient Information**

- Displays recipient's avatar, name, email, and client ID
- Auto-populated from the selected client

### 2. **Notification Type Selection**

Users can select one or both notification types:

- **System Notification**: In-app notification displayed to the client
- **Email Notification**: Email sent to the client's registered email address

### 3. **Content Editing**

- **Subject/Title**: Required field for notification subject
- **Message**: Required field with character limit (1000 characters)
- **Email Template**: Optional dropdown for pre-defined email templates when sending emails
  - Welcome Email
  - Deposit Confirmation
  - Withdrawal Notification
  - KYC Update
  - Promotional Offer

### 4. **Delivery Schedule**

Two delivery options:

- **Send Immediately**: Deliver notification right away
- **Schedule for Later**: Choose specific date and time for delivery

### 5. **Priority Levels**

Four priority options:

- Low
- Normal (default)
- High
- Urgent

### 6. **Preview Feature**

Preview how the notification will appear before sending:

- System notification preview shows in-app appearance
- Email preview shows email format with header and body

### 7. **Form Validation**

- Required fields validation
- Character limit enforcement
- Future date/time validation for scheduled notifications
- At least one notification type must be selected

## Usage

### Integration in ClientsList.vue

```vue
<template>
  <!-- Notification Button in Actions Column -->
  <button
    class="btn-action btn-notification"
    @click.stop="sendNotification(client)"
  >
    <i class="fas fa-bell"></i>
  </button>

  <!-- Modal Component -->
  <SendNotificationModal
    v-if="selectedClientForNotification"
    v-model="showSendNotificationModal"
    :recipient="selectedClientForNotification"
    @send="handleSendNotification"
  />
</template>

<script setup>
import SendNotificationModal from "@/components/client/SendNotificationModal.vue";

const showSendNotificationModal = ref(false);
const selectedClientForNotification = ref(null);

const sendNotification = (client) => {
  selectedClientForNotification.value = client;
  showSendNotificationModal.value = true;
};

const handleSendNotification = async (notificationData) => {
  // Handle notification sending
  // notificationData contains:
  // - recipientId
  // - recipientEmail
  // - recipientName
  // - sendSystemNotification (boolean)
  // - sendEmail (boolean)
  // - subject
  // - message
  // - emailTemplate
  // - scheduleType ('immediate' or 'scheduled')
  // - scheduleDateTime (ISO string if scheduled)
  // - priority ('low', 'normal', 'high', 'urgent')
};
</script>
```

## Notification Data Structure

When the user clicks "Send Notification", the component emits a `send` event with the following data structure:

```javascript
{
  recipientId: 123,
  recipientEmail: "client@example.com",
  recipientName: "John Doe",
  sendSystemNotification: true,
  sendEmail: true,
  subject: "Important Update",
  message: "Your account has been updated...",
  emailTemplate: "kyc", // or empty string for custom
  scheduleType: "scheduled", // or "immediate"
  scheduleDateTime: "2024-12-25T10:00", // ISO format, null if immediate
  priority: "high"
}
```

## Backend Integration (To Be Implemented)

When implementing the backend API, create an endpoint to handle this data:

```
POST /api/clients/notifications/send

Request Body: {
  recipientId: number,
  recipientEmail: string,
  recipientName: string,
  sendSystemNotification: boolean,
  sendEmail: boolean,
  subject: string,
  message: string,
  emailTemplate: string,
  scheduleType: string,
  scheduleDateTime: string | null,
  priority: string
}

Response: {
  success: boolean,
  message: string,
  notificationId?: number
}
```

## Styling

The component uses a modern, gradient-based design matching the application's theme:

- Primary gradient: `#667eea` to `#764ba2`
- Success states: Green tones
- Warning states: Orange/amber tones
- Error states: Red tones
- Fully responsive design with mobile support

## Accessibility

- Keyboard navigation support
- Focus states for all interactive elements
- Screen reader friendly labels
- Clear error messages
- Visual feedback for all actions

## Future Enhancements

1. **Rich Text Editor**: Upgrade message input to support formatting
2. **Attachment Support**: Allow file attachments for emails
3. **Template Management**: UI for creating/editing email templates
4. **Notification History**: Track sent notifications
5. **Bulk Notifications**: Send to multiple clients at once
6. **Notification Templates**: Pre-defined notification templates
7. **Variables/Placeholders**: Dynamic content like `{{clientName}}`
8. **Notification Analytics**: Track open rates, click rates, etc.

## Notes

- This is a frontend-only implementation
- No database or backend API calls are included
- All validations are client-side
- Currently uses browser alerts for success/error messages (can be replaced with toast notifications)
- Date/time picker uses native HTML5 inputs (can be upgraded to custom date picker components)
