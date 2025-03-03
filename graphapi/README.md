# Was this script helpful to you? Please donate:

I put a lot of work into these scripts so please donate if you can. Even $1 helps!

PayPal: alex@alexandermirvis.com

CashApp / Venmo: LynxGeekNYC

BitCoin: bc1q8sthd96c7chhq5kr3u80xrxs26jna9d8c0mjh7


# Below is a feature-rich, example OpenCart 3.x extension that goes beyond a simple proof-of-concept. It includes:

Auto-posting to Facebook and Instagram when you add or edit a product.
Manual “Post Now” button on the product form (so you can selectively post).
Multi-image support (optionally post multiple product images).
Central “Post Log” in the admin to see which products were posted, plus any errors.
Robust configuration (tokens, placeholders, multi-image toggle, auto/manual mode).
Basic error handling and logging – if the post fails, you can see the cause.
Disclaimer: This is a comprehensive example you can adapt. Actual production use may require further token-management (especially refreshing long-lived tokens), better error-handling, or security hardening. Always test carefully in a dev environment first.

# Below is a suggested directory layout inside a folder, e.g., advanced_autopost/. You’ll zip up the upload/ folder to install via the OpenCart Extension Installer.

advanced_autopost/
└── upload/
    ├── admin/
    │   ├── controller/
    │   │   └── extension/
    │   │       └── module/
    │   │           ├── advanced_autopost.php      (Main module settings controller)
    │   │           ├── advanced_autopost_log.php  (Controller for viewing post logs)
    │   │           └── advanced_autopost_button.php (Handles the manual "Post Now" action)
    │   ├── language/
    │   │   └── en-gb/
    │   │       └── extension/
    │   │           └── module/
    │   │               ├── advanced_autopost.php
    │   │               └── advanced_autopost_log.php
    │   ├── model/
    │   │   └── extension/
    │   │       └── module/
    │   │           └── advanced_autopost.php      (Model for logs, posting methods, etc.)
    │   └── view/
    │       └── template/
    │           └── extension/
    │               └── module/
    │                   ├── advanced_autopost.twig (Admin module config form)
    │                   └── advanced_autopost_log.twig (Log viewer)
    ├── system/
    │   └── config/
    └── ocmod.xml (Optional, if you need to modify product form twig to add a "Post Now" button)

# SQL: 

Store post logs in a custom table: oc_advanced_autopost_log

CREATE TABLE IF NOT EXISTS `oc_advanced_autopost_log` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `platform` VARCHAR(50) NOT NULL,         -- 'facebook' or 'instagram'
  `status` ENUM('success','error') NOT NULL,
  `message` TEXT DEFAULT NULL,             -- error details or success confirmation
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

Adjust the table prefix (oc_) if you use something different.

# Installing & Testing
Zip the upload/ folder along with the optional ocmod.xml file if you plan to add the “Post Now” button automatically:

Go to Extensions > Installer in your OpenCart admin and upload the .ocmod.zip.

Go to Extensions > Extensions > Modules, find Advanced AutoPost: Facebook & Instagram, and Install it.

Click Edit on the module, configure:

Module Status = Enabled

Auto Posting Mode = Yes or No (yes = auto post on every save, no = only “Post Now”)

Facebook Page Access Token = (your token)

Instagram Access Token = (your IG token)

Instagram Business User ID = (the numeric ID for your IG business account)

Post Template = Check out our new product: {product_name} - {product_url} by {store_name}

Post Multiple Images? = Yes or No

Save your changes.

If you included the ocmod.xml, go to Extensions > Modifications and Refresh.

Check Catalog > Products. Edit or add a product.

If Auto Posting Mode is On, any save/insert triggers a post. If Off, you can use the “Post Now” button.

Check the AutoPost Logs under the module’s “View Post Logs” button for success/error results.
