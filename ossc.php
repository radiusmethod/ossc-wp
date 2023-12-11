<?php
/*
Plugin Name: Open Source Software Contributions
Plugin URI: https://github.com/radiusmethod/wp-ossc/
Description: Displays Pull Request links from GitHub for Open Source Software Contributions.
Author: pjaudiomv
Author URI: https://radiusmethod.com
Version: 1.0.1
Install: Drop this directory into the "wp-content/plugins/" directory and activate it.
*/
/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Sorry, but you cannot access this page directly.');
}

if (!class_exists("RmOssc")) {
    // phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
    class RmOssc
        // phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace
    {
        public function __construct()
        {
            if (is_admin()) {
                // Back end
                add_action("admin_menu", [$this, "rmOsscOptionsPage"]);
                add_action("admin_init", [$this, "rmOsscRegisterSettings"]);
            } else {
                // Front end
                add_action("wp_enqueue_scripts", [$this, "enqueueFrontendFiles"]);
                add_shortcode('ossc', [$this, "rmOsscFunc"]);
            }
        }

        public function enqueueFrontendFiles()
        {
            wp_enqueue_style('ossc-css', plugins_url('css/ossc.css', __FILE__), false, '1.0.1', false);
        }

        public function rmOsscRegisterSettings()
        {
            add_option('rmOsscGithubApiKey', 'Github API Key.');
            add_option('githubRepos', '');
            add_option('githubUsers', '');
            register_setting('rmOsscOptionGroup', 'rmOsscGithubApiKey', 'rmOsscCallback');
            register_setting('rmOsscOptionGroup', 'githubRepos', 'rmOsscCallback');
            register_setting('rmOsscOptionGroup', 'githubUsers', 'rmOsscCallback');
        }

        public function rmOsscOptionsPage()
        {
            add_options_page('OSSC', 'OSSC', 'manage_options', 'rm-ossc', array(
                &$this,
                'rmOsscAdminOptionsPage'
            ));
        }
        public function rmOsscAdminOptionsPage()
        {
            ?>
            <div class="ossc_admin_div">
                <h2>Open Source Software Contributions</h2>
                <p>You must activate a github personal access token to use this plugin. Instructions can be found here <a herf="https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token">https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token</a>.</p>
                <form method="post" action="options.php">
                    <?php settings_fields('rmOsscOptionGroup'); ?>
                    <table class="ossc_table">
                        <tr class="ossc_tr">
                            <th scope="row"><label for="rmOsscGithubApiKey">GitHub API Token</label></th>
                            <td class="ossc_td"><input type="text" id="rmOsscGithubApiKey" name="rmOsscGithubApiKey" value="<?php echo get_option('rmOsscGithubApiKey'); ?>" /></td>
                        </tr>
                        <tr class="ossc_tr">
                            <th scope="row"><label for="githubRepos">Github Repos (Comma Separated String)</label></th>
                            <td class="ossc_td"><input type="text" id="githubRepos" name="githubRepos" value="<?php echo get_option('githubRepos'); ?>" /></td>
                        </tr>
                        <tr class="ossc_tr">
                            <th scope="row"><label for="githubUsers">Github Users (Comma Separated String)</label></th>
                            <td class="ossc_td"><input type="text" id="githubUsers" name="githubUsers" value="<?php echo get_option('githubUsers'); ?>" /></td>
                        </tr>
                    </table>
                    <?php  submit_button(); ?>
                </form>
            </div>
            <?php
        }

        public function rmOssc()
        {
            $this->__construct();
        }

        public function rmOsscFunc($atts = [])
        {
            $content = '<div class="ossc_div">';

            $githubRepos = explode(",", get_option('githubRepos'));
            $githubUsers = explode(",", get_option('githubUsers'));

            foreach ($githubRepos as $repo) {
                $repoName = explode("/", $repo)[1];
                $content .= '<p><strong><a href="https://github.com/' . $repo . '" target="_blank" data-type="URL" rel="noreferrer noopener">' . $repoName . '</a></strong></p>';
                $items = $this->githubPullRequests($repo, $githubUsers);
                if (is_string($items)) {
                    return $items;
                }
                usort($items['items'], function ($a, $b) {
                    return strnatcasecmp(strtotime($b['closed_at']), strtotime($a['closed_at']));
                });
                $content .= '<ul class="ossc_ul">';
                foreach ($items['items'] as $item) {
                    $content .= '<li class="ossc_li">' . '<a target="_blank" rel="noopener noreferrer" href="' . $item['html_url'] . '">' . $item['html_url'] . '</a></li>';
                }
                $content .= "</ul>";
            }

            $content .= "</div>";
            return $content;
        }

        public function githubPullRequests($repo, $users = null)
        {
            $userString = '';
            if ($users) {
                foreach ($users as $index => $user) {
                    $connector = $index === 0 ? '+author:' : '+or+author:';
                    $userString .= $connector . $user;
                }
            }

            $results = $this->get("https://api.github.com/search/issues?q=is:pr+is:merged+repo:$repo$userString");
            $httpcode = wp_remote_retrieve_response_code($results);
            $response_message = wp_remote_retrieve_response_message($results);
            if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304 && !empty($response_message)) {
                return 'Problem Connecting to Server!';
            }
            $body = wp_remote_retrieve_body($results);
            return json_decode($body, true);
        }

        public function get($url)
        {
            $gitHubApiKey = get_option('rmOsscGithubApiKey');

            $args = array(
                'timeout' => '120',
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => "Bearer $gitHubApiKey",
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:105.0) Gecko/20100101 Firefox/105.0 +rmOssc'
                ]
            );

            return wp_remote_get($url, $args);
        }
    }
}

if (class_exists("RmOssc")) {
    $rmOssc_instance = new RmOssc();
}
