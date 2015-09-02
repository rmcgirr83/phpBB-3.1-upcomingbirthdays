Upcoming Birthday List
===============

phpBB 3.1 Upcoming Birthday List extension

Extension will display those users who have a birthday coming as per the settings in the ACP (under the "load" and "Board features" settings).  Loading of Birthdays must be set to display in the ACP.  This is a port of the phpBB 3.0.x mod written by Lefty74.

[![Build Status](https://travis-ci.org/RMcGirr83/phpBB-3.1-upcomingbirthdays.svg?branch=master)](https://travis-ci.org/RMcGirr83/phpBB-3.1-upcomingbirthdays)

## Installation

### 1. clone
Clone (or download and move) the repository into the folder ext/rmcgirr83/upcomingbirthdays:

```
cd phpBB3
git clone https://github.com/RMcGirr83/phpBB-3.1-upcomingbirthdays.git ext/rmcgirr83/upcomingbirthdays/
```

### 2. activate
Go to admin panel -> tab customise -> Manage extensions -> enable Upcoming Birthdays

Within the Admin panel visit the Load or Board Features settings and choose the number of days to look forward for birthdays.

## Update instructions:
1. Go to you phpBB-Board > Admin Control Panel > Customise > Manage extensions > Upcoming Birthdays: disable
2. Delete all files of the extension from ext/rmcgirr83/upcomingbirthdays
3. Upload all the new files to the same locations
4. Go to you phpBB-Board > Admin Control Panel > Customise > Manage extensions > Upcoming Birthdays: enable
5. Purge the board cache