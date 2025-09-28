DragonByte Credits for XenForo 2.2.0+
=====================================

![Deploy](https://github.com/DragonByteTech/xf2credits/workflows/Deploy/badge.svg) ![Lint](https://github.com/DragonByteTech/xf2credits/workflows/Lint/badge.svg)  
  
Description
-----------

A fully features points/credits system allowing your members to earn or buy points.

Requirements
------------

- PHP 7.2.0+

Recommendations
---------------

- PHP 7.4.0+
- DragonByte Shop v6.1.0+

Options
-------

#### DragonByte Tech: Credits

| Name | Description |
|---|---|
| Smart event negation \[EXPERIMENTAL\] | If enabled, a negation event trigger (f.ex. a post being deleted) will delete the existing transaction instead of inserting a new transaction. This does not work for all event triggers.   If multiple transactions are found, it will negate the event using a new transaction instead.   You can also override this feature by setting the "Negation amount" to a value other than 0. |
| Event triggering | If more than one event per currency applies to a situation, choose whether the highest amount or all of them will be triggered. |
| Enable events | Master toggle for all events should you want to temporarily disable the whole system. Does not disable Displays. |
| Charge event trigger: Content tag | This BBCode will be used for designating charged content. Change this to something else to take over the tags from other content hiding plugins. Blank out this setting to disable the charged content functionality, which would expose such content for free. |
| Charge event trigger: Currency | The currency that will be used for charged content transactions. Make sure corresponding events are set to use this currency as well. |
| Donate event trigger: Currency rounding | To ease rounding issues, you can set a number of decimals that the target currency will be rounded up to that adjusts the source currency amounts accordingly when transferring or donating currency.   Use 0 for whole number amounts. |
| Interest event trigger: Interval | Number of days between each interest event trigger. |
| Conversation event trigger: Trigger event for all participants | If unchecked, the event will only occur once no matter how many recipients are attached to a single message. |
| Paycheck event trigger: Interval | Number of days between each paycheck event trigger. |
| Purchase event trigger: Currency | The currency you're selling your credits in. |
| Revive event trigger: Definition of old thread | Time (in seconds) since the last reply before a thread is considered "old". |
| Taxation event trigger: Interval | Number of days between each taxation event trigger. |
| Taxation event trigger: User | If you wish to send the taxed amount to another user, enter the user name here. |
| Navbar tab |  |
| Size multipliers | Choose whether it's the number of words or number of characters in a message that will be used for size multipliers. This only affects certain event triggers. |
| Transactions per page | The currency page lists the latest transactions for each user. This is the number of transactions to show before splitting to the next page. |

#### Debug options (Debug only)

| Name | Description |
|---|---|
| DragonByte Credits License Key | This is the license key associated with your product. Removing this will affect your ability to use the free "Product Manager" product to check for updates. |

Permissions
-----------

#### DragonByte Credits moderator permissions

- Adjust currencies
- View any transaction log entry
- View unapproved transactions
- Bypass "Charge" tags
- Bypass currency privacy
- Approve / unapprove transactions

#### DragonByte Credits permissions

- View
- Trigger events
- Maximum "Charge" tags per post

Admin Permissions
-----------------

- Manage DragonByte Credits

BB Codes
--------

| Name | Tag | Description | Example |
|---|---|---|---|
| Charge | `CHARGE` | Charging users to view your content. | \[CHARGE=5\]It costs 5 credits to see this text.\[/CHARGE\] |

Widget Positions
----------------

| Position | Description |
|---|---|
| DragonByte Tech: Credits - Transactions Sidebar (`dbtech_credits_transactions_sidebar`) | Position in the sidebar while viewing the Credits transactions. |

Widget Definitions
------------------

| Definition | Description |
|---|---|
| DragonByte Credits: Richest Users (`dbtech_credits_richest`) | Displays a block containing the top X richest users. |
| DragonByte Credits: Wallet (`dbtech_credits_wallet`) | Displays a block containing the available currencies for the current user. |

Cron Entries
------------

| Name | Run on... | Run at hours | Run at minutes |
|---|---|---|---|
| DragonByte Credits: Trigger birthday event | Any day of the month | 12AM | 0 |
| DragonByte Credits: Daily Credits | Any day of the month | 12AM | 5 |
| DragonByte Credits: Expiring Credits | Any day of the month | Any | 0 |

CLI Commands
------------

| Command | Description |
|---|---|
| `xf-rebuild:dbtech-credits-transactions` | Rebuilds the transaction records. |