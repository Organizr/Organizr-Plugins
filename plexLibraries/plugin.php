<?php
// PLUGIN INFORMATION
$GLOBALS['plugins']['plexlibraries'] = array( // Plugin Name
	'name' => 'Plex Libraries', // Plugin Name
	'author' => 'TehMuffinMoo', // Who wrote the plugin
	'category' => 'Library Management', // One to Two Word Description
	'link' => 'https://github.com/Organizr/Organizr-Plugins/tree/main/plexLibraries', // Link to plugin info
	'license' => 'personal', // License Type use , for multiple
	'idPrefix' => 'PLEXLIBRARIES', // html element id prefix (All Uppercase)
	'configPrefix' => 'PLEXLIBRARIES', // config file prefix for array items without the hypen (All Uppercase)
	'version' => '1.0.4', // SemVer of plugin
	'image' => 'data/plugins/plexLibraries/logo.png', // 1:1 non transparent image for plugin
	'settings' => true, // does plugin need a settings modal?
	'bind' => true, // use default bind to make settings page - true or false
	'api' => 'api/v2/plugins/plexlibraries/settings', // api route for settings page (All Lowercase)
	'homepage' => false // Is plugin for use on homepage? true or false
);

class plexLibrariesPlugin extends Organizr
{
	public function _pluginGetSettings()
	{
		$libraryList = [['name' => 'Refresh page to update List', 'value' => '', 'disabled' => true]];
		if ($this->config['plexID'] !== '' && $this->config['plexToken'] !== '') {
			$libraryList = [];
			$loop = $this->plexLibraryList('key')['libraries'];
			foreach ($loop as $key => $value) {
				$libraryList[] = ['name' => $key, 'value' => $value];
			}
		}
		$this->setGroupOptionsVariable();
		return array(
			'Settings' => array(
				$this->settingsOption('token', 'plexToken'),
				$this->settingsOption('button', '', ['label' => 'Get Plex Token', 'icon' => 'fa fa-ticket', 'text' => 'Retrieve', 'attr' => 'onclick="PlexOAuth(oAuthSuccess,oAuthError, null, \'#PLEXLIBRARIES-settings-page [name=plexToken]\')"']),
				$this->settingsOption('password-alt', 'plexID', ['label' => 'Plex Machine']),
				$this->settingsOption('button', '', ['label' => 'Get Plex Machine', 'icon' => 'fa fa-id-badge', 'text' => 'Retrieve', 'attr' => 'onclick="showPlexMachineForm(\'#PLEXLIBRARIES-settings-page [name=plexID]\')"']),
				$this->settingsOption('auth', 'PLEXLIBRARIES-pluginAuth'),
				$this->settingsOption('input', 'plexAdmin', ['label' => 'Plex Admin Username or Email']),
				$this->settingsOption('plex-library-include', 'PLEXLIBRARIES-librariesToInclude', ['options' => $libraryList]),
				$this->settingsOption('switch', 'PLEXLIBRARIES-disableModal-include', ['label' => 'Disable access from user dropdown menu', 'help' => 'Enabling this will turn off the button for the Plex Libraries Plugin within the user dropdown menu in the top right hand corner. This should be used when configuring the plugin as an Organizr Tab, for more info see the About section of the plugin settings.'])

			),
			'About' => array (
				$this->settingsOption('notice', '', ['title' => 'Information', 'body' => '
				<h3 lang="en">Plugin Information</h3>
				<p>This plugin allows you to enable/disable plex shares for your users from within Organizr.</p>
				<br/>
				<h3>Using it as an Organizr Tab</h3>
				<p>If you prefer, you can use the Plex Libraries plugin as an Organizr tab instead of accessing it via the dropdown menu in the top right-hand corner.</p>
				<p>To configure this, create a new Tab in Organizr using the URL below. The tab <b>must</b> be created using the <u>Organizr</u> Type, using iFrame will not work as expected.</p>
				<code class="elip hidden-xs">/api/v2/page/plex_libraries</code>
				<h4>When using this plugin as an Organizr tab, we suggest disabling access to the plugin via the user dropdown menu to avoid unexpected issues. You can do this using the toggle in the plugin settings.</h4>'
				]),
			)
		);
	}
	
	public function _pluginLaunch()
	{
		$user = $this->getUserById($this->user['userID']);
		if ($user) {
			if ($user['plex_token'] !== null) {
				$this->setResponse(200, 'User approved for plugin');
				return true;
			}
		}
		$this->setResponse(401, 'User not approved for plugin');
		return false;
	}
	
	public function plexLibrariesPluginGetPlexShares($includeAll = false, $userId = "")
	{
		$this->setLoggerChannel('Plex Libraries Plugin');
		if (empty($this->config['plexToken'])) {
			$this->setResponse(409, 'plexToken is not setup');
			$this->logger->warning('plexToken is not setup');
			return false;
		}
		$headers = array(
			'Content-type: application/xml',
			'X-Plex-Token' => $this->config['plexToken'],
		);
		// Check if user is Plex Admin
		if ((strtolower($this->user['username']) == strtolower($this->config['plexAdmin']) || strtolower($this->user['email']) == strtolower($this->config['plexAdmin'])) && !$userId) {
			$url = 'https://plex.tv/api/servers/' . $this->config['plexID'] . '/shared_servers/';
			try {
				$response = Requests::get($url, $headers, []);
				if ($response->success) {
					libxml_use_internal_errors(true);
					$plex = simplexml_load_string($response->body);
					$libraryList = array();
					foreach ($plex->SharedServer as $child) {
						if (!empty($child['username'])) {
							$libraryList[(string)$child['username']]['username'] = (string)$child['username'];
							$libraryList[(string)$child['username']]['email'] = (string)$child['email'];
							$libraryList[(string)$child['username']]['id'] = (string)$child['id'];
							$libraryList[(string)$child['username']]['userID'] = (string)$child['userID'];
							foreach ($child->Section as $library) {
								$library = current($library->attributes());
								$libraryList[(string)$child['username']]['libraries'][] = $library;
							}
						}
					}
					$libraryList = array_change_key_case($libraryList, CASE_LOWER);
					ksort($libraryList);
					$apiData = [
						'plexAdmin' => true,
						'libraryData' => $libraryList
					];
					$this->setResponse(200, null, $apiData);
					return $apiData;
				} else {
					$this->setResponse(500, 'Plex error');
					$this->logger->warning('Plex Error',$response);
					return false;
				}
			} catch (Requests_Exception $e) {
				$this->logger->error($e);
				$this->setAPIResponse('error', 'PlexLibraries Plugin - Error: ' . $e->getMessage(), 400);
				return false;
			}
		} else {
			$searchTerm = ($userId) ?: $this->user['email'];
			$searchKey = ($userId) ? 'shareId' : 'email';
			$plexUsers = $this->allPlexUsers(false, true);
			$key = array_search($searchTerm, array_column($plexUsers, $searchKey));
			if ($key !== false) {
				$url = 'https://plex.tv/api/servers/' . $this->config['plexID'] . '/shared_servers/' . $plexUsers[$key]['shareId'];
			} else {
				$this->setResponse(404, 'User Id was not found in Plex Users');
				return false;
			}
			try {
				$response = Requests::get($url, $headers, array());
				if ($response->success) {
					libxml_use_internal_errors(true);
					$plex = simplexml_load_string($response->body);
					$libraryList = array();
					foreach ($plex->SharedServer as $child) {
						if (!empty($child['username'])) {
							$libraryList[(string)$child['username']]['username'] = (string)$child['username'];
							$libraryList[(string)$child['username']]['email'] = (string)$child['email'];
							$libraryList[(string)$child['username']]['id'] = (string)$child['id'];
							$libraryList[(string)$child['username']]['shareId'] = (string)$plexUsers[$key]['shareId'];
							foreach ($child->Section as $library) {
								$library = current($library->attributes());
								if (!$includeAll) {
									$librariesToInclude = explode(',', $this->config['PLEXLIBRARIES-librariesToInclude']);
									if (in_array($library['key'], $librariesToInclude)) {
										$libraryList[(string)$child['username']]['libraries'][] = $library;
									}
								} else {
									$libraryList[(string)$child['username']]['libraries'][] = $library;
								}
							}
						}
					}
					$libraryList = array_change_key_case($libraryList, CASE_LOWER);
					$apiData = [
						'plexAdmin' => false,
						'libraryData' => $libraryList
					];
					$this->setResponse(200, null, $apiData);
					return $apiData;
				} else {
					$this->logger->warning('Plex Error',$response);
					$this->setResponse(500, 'Plex Error', $response->body);
					return false;
				}
			} catch (Requests_Exception $e) {
				$this->logger->error($e);
				$this->setAPIResponse('error', 'PlexLibraries Plugin - Error: ' . $e->getMessage(), 400);
				return false;
			}
		}
	}
	
	public function plexLibrariesPluginUpdatePlexShares($userId, $action, $shareId)
	{
		$this->setLoggerChannel('Plex Libraries Plugin');
		if (!$userId) {
			$this->setResponse(409, 'User Id not supplied');
			return false;
		}
		if (!$action) {
			$this->setResponse(409, 'Action not supplied');
			return false;
		}
		if (!$shareId) {
			$this->setResponse(409, 'Share Id not supplied');
			return false;
		}
		if (!$this->qualifyRequest(1)) {
			$plexUsers = $this->allPlexUsers(false, true);
			$key = array_search($this->user['email'], array_column($plexUsers, 'email'));
			if (!$key) {
				$this->setResponse(404, 'User Id was not found in Plex Users');
				return false;
			} else {
				if ($plexUsers[$key]['shareId'] !== $userId) {
					$this->setResponse(401, 'You are not allowed to edit someone else\'s plex share');
					$this->logger->notice('Editing someone else\'s plex share is not permitted.',$userId);
					return false;
				}
			}
		}
		$Shares = $this->plexLibrariesPluginGetPlexShares(true, $userId);
		$NewShares = array();
		if ($Shares) {
			if (isset($Shares['libraryData'])) {
				foreach ($Shares['libraryData'] as $key => $Share) {
					foreach ($Share['libraries'] as $library) {
						if ($library['shared'] == 1) {
							$ShareString = (string)$library['id'];
							if ($action == 'share') {
								$NewShares[] = $ShareString;
								$Msg = 'Enabled share';
							} else {
								$Msg = 'Disabled share';
								if ($ShareString !== $shareId) {
									$NewShares[] = $ShareString;
								}
							}
						}
					}
				}
				if ($action == 'share') {
					if (!in_array($shareId, $NewShares)) {
						$NewShares[] = $shareId;
					}
				}
			}
		}
		if (empty($NewShares)) {
			$this->setResponse(409, 'You must have at least one share.');
			return false;
		} else {
			$http_body = [
				"server_id" => $this->config['plexID'],
				"shared_server" => [
					"library_section_ids" => $NewShares
				]
			];
			if ($userId) {
				$url = 'https://plex.tv/api/servers/' . $this->config['plexID'] . '/shared_servers/' . $userId . '?X-Plex-Token=' . $this->config['plexToken'];
			}
			$headers = [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			];
			try {
				$response = Requests::put($url, $headers, json_encode($http_body), []);
				if ($response->success) {
					$this->setAPIResponse('success', $Msg, 200, $http_body);
					return $http_body;
				} else {
					$this->logger->warning('Plex Error',$response);
					$this->setAPIResponse('error', 'PlexLibraries Plugin - Error: Plex Error', 400);
					return false;
				}
			} catch (Requests_Exception $e) {
				$this->logger->error($e);
				$this->setAPIResponse('error', 'PlexLibraries Plugin - Error: ' . $e->getMessage(), 400);
				return false;
			}
		}
	}
}
