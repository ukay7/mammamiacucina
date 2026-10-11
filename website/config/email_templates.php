<?php
return [
 'verification'=>['label'=>'Website registration','subject'=>'Verify your {{site_name}} account','body'=>"Hello {{name}},\n\nWelcome to {{site_name}}. Please verify your email using the link below:\n\n{{verification_url}}\n\nThis link expires in {{expires_minutes}} minutes. Business accounts also require administrator approval.\n\nIf you did not create this account, ignore this email.\n\n{{site_name}}",'tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'verification_resend'=>['label'=>'Resend verification','subject'=>'Your new {{site_name}} verification link','body'=>"Hello {{name}},\n\nHere is your requested email verification link:\n\n{{verification_url}}\n\nThis link expires in {{expires_minutes}} minutes.\n\n{{site_name}}",'tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'password_reset'=>['label'=>'Forgot password / reset password','subject'=>'Reset your {{site_name}} password','body'=>"Hello {{name}},\n\nUse this link to choose a new password:\n\n{{reset_url}}\n\nThis link expires in {{expires_minutes}} minutes. If you did not request a reset, ignore this email. Your password will stay unchanged.\n\n{{site_name}}",'tokens'=>['name','site_name','reset_url','expires_minutes'],'required'=>'reset_url'],
 'pos_invitation'=>['label'=>'POS customer invitation','subject'=>'Verify your {{site_name}} customer account','body'=>"Hello {{name}},\n\nYour customer account has been created at {{site_name}}. Verify your email here:\n\n{{verification_url}}\n\nThis link expires in {{expires_minutes}} minutes. After verification, use Forgot password to set your password and access your orders. Business accounts also require administrator approval.\n\n{{site_name}}",'tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'admin_invitation'=>['label'=>'Admin-created customer invitation','subject'=>'Welcome to {{site_name}}','body'=>"Hello {{name}},\n\nAn administrator created your customer account. Verify your email here:\n\n{{verification_url}}\n\nThis link expires in {{expires_minutes}} minutes. You can use Forgot password to choose your own password. Business accounts also require administrator approval.\n\n{{site_name}}",'tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'admin_verification'=>['label'=>'Verification sent by admin','subject'=>'Verify your {{site_name}} email','body'=>"Hello {{name}},\n\nPlease verify your customer email using this link:\n\n{{verification_url}}\n\nThis link expires in {{expires_minutes}} minutes. After verification, you may use Forgot password if you need to set a password.\n\n{{site_name}}",'tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'smtp_test'=>['label'=>'SMTP test email','subject'=>'{{site_name}} — SMTP test','body'=>"Hello,\n\nThis is a test email from {{site_name}}. Your website successfully submitted this message using its saved SMTP settings.\n\nIf you received it, inbox delivery worked for this test.\n\n{{site_name}}",'tokens'=>['site_name'],'required'=>null],
 'business_verification'=>['label'=>'Business registration / invitation','subject'=>'Verify your email — business account under review','body'=>'Hello {{name}},

Welcome to {{site_name}}. Your business account is under review. Please verify your email while our team reviews your application:

{{verification_url}}

This link expires in {{expires_minutes}} minutes. Purchasing requires both email verification and administrator approval. We will email you when your account is active. If an administrator created your account, use Forgot password to choose your password after verification.

Thank you for your patience.','tokens'=>['name','site_name','verification_url','expires_minutes'],'required'=>'verification_url'],
 'business_approved'=>['label'=>'Business approved — verification required','subject'=>'Your business application is approved','body'=>'Hello {{name}},

Your {{site_name}} business application has been approved. Verify your email to activate purchasing:

{{verification_url}}

This link expires in 48 hours. If it expires, sign in and request a new verification email.','tokens'=>['name','site_name','verification_url'],'required'=>'verification_url'],
 'business_active'=>['label'=>'Business account active','subject'=>'Your {{site_name}} business account is now active','body'=>'Hello {{name}},

Your email is verified and your business account has been approved. You can now sign in and start purchasing with your business pricing:

{{account_url}}

Thank you for choosing {{site_name}}.','tokens'=>['name','site_name','account_url'],'required'=>'account_url'],
 'order_confirmation'=>['label'=>'Order confirmation + PDF','subject'=>'Order {{order_number}} confirmed','body'=>'Hello {{name}},

{{environment}}
We have received your order. Your order summary is attached as a PDF.

Order: {{order_number}}
Status: {{status}}
Payment: {{payment_status}} — {{payment_method}}
Order total: {{total}}
Net received: {{received}}
{{balance}}

{{fulfillment}}

{{instructions}}

Track your order:
{{order_url}}

Customer portal (receipt uploads and order details):
{{account_url}}

{{site_name}}','tokens'=>['name','site_name','order_number','status','payment_status','total','received','balance','amount','payment_method','fulfillment','order_url','account_url','instructions','environment'],'required'=>'order_url'],
 'payment_received'=>['label'=>'Verified payment + PDF receipt','subject'=>'Payment received for {{order_number}}','body'=>'Hello {{name}},

{{environment}}
We have verified a payment of {{amount}} for your order. Your updated payment record is attached as a PDF.

Order: {{order_number}}
Status: {{status}}
Payment: {{payment_status}} — {{payment_method}}
Order total: {{total}}
Net received: {{received}}
{{balance}}

{{fulfillment}}

{{instructions}}

Track your order:
{{order_url}}

Customer portal (receipt uploads and order details):
{{account_url}}

{{site_name}}','tokens'=>['name','site_name','order_number','status','payment_status','total','received','balance','amount','payment_method','fulfillment','order_url','account_url','instructions','environment'],'required'=>'order_url'],
 'order_status'=>['label'=>'Order status update','subject'=>'Order {{order_number}} — {{status}}','body'=>'Hello {{name}},

{{environment}}
Your order status has changed.

Order: {{order_number}}
Status: {{status}}
Payment: {{payment_status}} — {{payment_method}}
Order total: {{total}}
Net received: {{received}}
{{balance}}

{{fulfillment}}

{{instructions}}

Track your order:
{{order_url}}

Customer portal (receipt uploads and order details):
{{account_url}}

{{site_name}}','tokens'=>['name','site_name','order_number','status','payment_status','total','received','balance','amount','payment_method','fulfillment','order_url','account_url','instructions','environment'],'required'=>'order_url'],
];
