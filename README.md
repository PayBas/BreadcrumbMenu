Breadcrumb Menu for phpBB 3.1
==========

Extension for phpBB 3.1 to turn the breadcrumb navigation into a forum tree menu.

![Screenshot](screenshot.png)

## Features
- Adds a drop-down menu to each breadcrumb (using jQuery UI), containing the sibling and child forums.
- Triggers on mouse-enter, no clicks required (this keeps the breadcrumbs working as normal when clicking on them).
- Auto-hide when the user moves the cursor outside of the menu. 

#### Requirements
- phpBB 3.1-RC1 or higher
- PHP 5.3.3 or higher

#### Languages supported
- No language files included/necessary

#### To do list
- Improve responsiveness and touch-events
- Perhaps make an ACP module so admins can set certain vars (such as animations and timers).

## Installation
1. [Download the latest release](https://github.com/PayBas/BreadcrumbMenu/releases) and unzip it.
2. Copy the entire contents from the unzipped folder to `phpBB/ext/paybas/breadcrumbmenu/`.
3. Navigate in the ACP to `Customise -> Manage extensions`.
4. Find `Breadcrumb Menu` under "Disabled Extensions" and click `Enable`.

## Uninstallation
1. Navigate in the ACP to `Customise -> Manage extensions`.
2. Click the `Disable` link for `Breadcrumb Menu`.
3. To permanently uninstall, click `Delete Data`, then delete the `breadcrumbmenu` folder from `phpBB/ext/paybas/`.

### License
[GNU General Public License v2](http://opensource.org/licenses/GPL-2.0)

© 2014 - PayBas