<?php

namespace App\Enums;

enum NotificationEvent: string
{
    case IncidentOpened = 'incident_opened';
    case IncidentResolved = 'incident_resolved';
    case ChannelVerification = 'channel_verification';
    case TestMessage = 'test_message';
    case SubscriptionReminder = 'subscription_reminder';
}
