WP_Twitter_Client &mdash; Library to help WordPress developer working with Twitter REST API.

## Features

* Small and readable code (just one main file, three hundred lines including comments).
* Use WordPress HTTP request API.
* Filters available to manipulate OAuth parameters.
* Include examples for starting guide.
* NO PHP Session call, you decide how to make the token persistent (good example can be found in `examples`).

## Examples

The examples, found in `examples` directory are intended for starter kit and
learning purpose and SHOULD NOT be used for production site. Here's how to
play with the examples:

1. I encourage you to create a child theme from your active theme. Lets name it
   `twentytwelve_child`.

2. Go to `twentytwelve_child` directory and clone `WP_Twitter_Client` repo.

  ~~~text
  $ git clone https://github.com/gedex/WP_Twitter_Client.git
  ~~~

3. Include one of the example in `twentytwelve_child`'s `functions.php`.

  ~~~php
  require_once( STYLESHEETPATH . '/WP_Twitter_Client/examples/widget_home_timeline.php' );
  ~~~

4. Go to https://dev.twitter.com/apps, create an app and note the `consumer_key` and
   `consumer_secret`. Open the example you're going to use and find following lines:

   ~~~php
   define( 'CONSUMER_KEY',    'DL9ziNzAbLmShjW8sSYxw' );
   define( 'CONSUMER_SECRET', 'l5NCQTBHv4VNVAIx0rb6R1oRoh21XPuqiy0kAfw8xnQ' );
   ~~~~

   Replace it with your own `consumer_key` and `consumer_secret`.

It's best to start from `/WP_Twitter_Client/examples/authorization.php` as it contains
the basic OAuth flow used to obtain the access token.

### authorization.php

Example to demonstrate authorization using `WP_Twitter_Client`. This demo will add sub menu page
to the Settings menu where it renders a button to authorize the app. Once authorized,
you should be able to see the twitter screen_name.

### widget_home_timeline.php

Example to demonstrate rendering collection of the most recent Tweets and retweets posted by
the authenticating user and the users they follow using `WP_Twitter_Client`. This demo will add
sub menu page to the Settings menu where it renders a button to authorize the app. Once authorized,
you should be able to see the twitter screen_name and the widget will be able to fetch the tweets.


## Running Test

**WIP** and will be in `test` directory.

## License

Copyright (C) 2013  Akeda Bagus <admin@gedex.web.id>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
