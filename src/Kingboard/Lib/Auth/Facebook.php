<?php
namespace Kingboard\Lib\Auth;

/**
 * Facebook based authentication
 */
class Facebook extends Auth
{
    /**
     * @var string oauth2 token url
     */
    public static $host_url_token = "https://graph.facebook.com/oauth/access_token";

    /**
     * @var string oauth2 auth url (redirects here)
     */
    public static $host_url_code = "https://www.facebook.com/dialog/oauth";

    /**
     * @var string scope required for the api call, in googles case auth/userinfo.email
     */
    public static $scope = "email";

    /**
     * get scope
     * @static
     * @return string
     */
    public static function getScope()
    {
        return self::$scope;
    }

    /**
     * get code Url
     * @static
     * @return string
     */
    public static function getCodeUrl()
    {
        return self::$host_url_code;
    }

    /**
     * get token url
     * @static
     * @return string
     */
    public static function getTokenUrl()
    {
        return self::$host_url_token;
    }

    /**
     * execute the login
     * @static
     * @param array $config this providers config array from the registry
     * @return \Kingboard\Model\User
     */
    public static function login($config, $fake)
    {
        if (isset($_GET['error'])) {
            throw new \Exception("Could not login: " . $_GET['error']);
        }

        $tokens = \Kingboard\Lib\Auth\OAuth2\Consumer::getTokens(
            self::getTokenUrl(),
            $config['client_id'],
            $config['client_secret'],
            $_GET['code'],
            $config['redirect_url'],
            true
        );

        if (is_null($tokens)) {
            throw new \Exception("Error: could not access tokens");
        }

        $userinfo = json_decode(
            file_get_contents("https://graph.facebook.com/me?access_token=" . $tokens['access_token'])
        );

        if (is_null($userinfo)) {
            throw new \Exception("Error: could not access userinfo");
        }

        $user = \Kingboard\Model\User::findOne(array("username" => $userinfo->email));
        if (is_null($user)) {
            $user = new \Kingboard\Model\User();
            $user->username = $userinfo->email;
            $user->save();
        }

        $_SESSION["Kingboard_Auth"] = array("User" => $user);
        return $user;
    }
}
