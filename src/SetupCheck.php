<?php

namespace SMW;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 3.1
 *
 * @author mwjames
 */
class SetupCheck {

	/**
	 * @var []
	 */
	private $options = [];

	/**
	 * @var SetupFile
	 */
	private $setupFile;

	/**
	 * @var string
	 */
	private $errorType = '';

	/**
	 * @since 3.1
	 *
	 * @param array $vars
	 * @param SetupFile|null $setupFile
	 *
	 * @return boolean
	 */
	public function __construct( array $options, SetupFile $setupFile = null ) {
		$this->options = $options;
		$this->setupFile = $setupFile;

		if ( $this->setupFile === null ) {
			$this->setupFile =  new SetupFile();
		}
	}

	/**
	 * @since 3.1
	 *
	 * @return boolean
	 */
	public function isCli() {
		return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
	}

	/**
	 * @since 3.1
	 *
	 * @return boolean
	 */
	public function hasError() {

		$this->errorType = '';

		// When it is not a test run or run from the command line we expect that
		// the extension is registered using `enableSemantics`
		if ( !$this->isCli() && !defined( 'SMW_EXTENSION_LOADED' ) ) {
			$this->errorType = 'extensionload';
		} elseif ( $this->setupFile->inMaintenanceMode() ) {
			$this->errorType = 'maintenance';
		} elseif ( $this->setupFile->isGoodSchema() === false ) {
			$this->errorType = 'schema';
		}

		return $this->errorType !== '';
	}

	/**
	 * @since 3.1
	 *
	 * @param boolean $isCli
	 *
	 * @return boolean
	 */
	public function getError( $isCli = false ) {

		$error = [
			'title' => '',
			'content' => ''
		];

		if ( $this->errorType === 'extensionload' ) {
			$error = $this->extensionLoadError();
		} elseif ( $this->errorType === 'maintenance' ) {
			$error = $this->maintenanceError( $this->setupFile->getMaintenanceMode() );
		} elseif ( $this->errorType === 'schema' ) {
			$error = $this->schemaError();
		}

		if ( $isCli === false ) {
			$content = $this->buildHTML( $error );
			header( 'Content-type: text/html; charset=UTF-8' );
			header( 'Content-Length: ' . strlen( $content ) );
			header( 'Cache-control: none' );
			header( 'Pragma: no-cache' );
		} else {
			$content = $error['title'] . "\n\n" . $error['content'];
			$content = strip_tags( trim( $content ) );
		}

		return $content;
	}

	/**
	 * @since 3.1
	 *
	 * @param boolean $isCli
	 */
	public function showErrorAndAbort( $isCli = false ) {

		echo $this->getError( $isCli );

		if ( ob_get_level() ) {
			ob_flush();
			flush();
			ob_end_clean();
		}

		die();
	}

	private function extensionLoadError() {

		$content = '<h3>' . Message::get( 'smw-upgrade-release' ) . '</h3>' .
				'<p>' . $this->options['version'] . '</p>' .
				'<h3 class="section"><span class="title">' . Message::get( 'smw-upgrade-error-why-title' ) . '</span></h3>' .
				'<p>' . Message::get( 'smw-extensionload-error-why-explain', Message::PARSE ) . '</p>' .
				'<h3 class="section"><span class="title">' . Message::get( 'smw-extensionload-error-how-title' ) . '</span></h3>' .
				'<p>' . Message::get( 'smw-extensionload-error-how-explain', Message::PARSE ) . '</p>';

		$error = [
			'title' => Message::get( 'smw-upgrade-error-title' ),
			'content' => $content,
			'borderColor' => '#F44336'
		];

		return $error;
	}

	private function maintenanceError( $maintenance ) {

		$progress = '';

		if ( is_array( $maintenance ) && $maintenance !== []  ) {
			foreach ( $maintenance as $key => $value ) {
				$progress = $this->progress( Message::get( "smw-upgrade-progress-$key" ), $value );
			}
		}

		$content = '<p>' . Message::get( 'smw-upgrade-maintenance-note', Message::PARSE ) . '</p>' .
			'<h3>' . Message::get( 'smw-upgrade-release' ) . '</h3>' .
			'<p>' . $this->options['version'] . '&nbsp;(' . $this->options['smwgUpgradeKey'] . ')' . '</p>' .
			'<h3 class="section"><span class="title">' . Message::get( 'smw-upgrade-maintenance-why-title' ) . '</span></h3>' .
			'<p>' . Message::get( 'smw-upgrade-maintenance-explain', Message::PARSE ) . '</p>' .
			'<h3 class="section"><span class="title">' . Message::get( 'smw-upgrade-progress' ) . '</span></h3>' .
			'<p>' . $progress . Message::get( 'smw-upgrade-progress-explain', Message::PARSE ) . '</p>';

		$error = [
			'title' => Message::get( 'smw-upgrade-maintenance-title' ),
			'content' => $content,
			'borderColor' => '#ffc107'
		];

		return $error;
	}

	private function schemaError() {

		$content = '<p>' . Message::get( [ 'smw-upgrade-error', '' ], Message::PARSE ) . '</p>' .
				'<h3>' . Message::get( 'smw-upgrade-release' ) . '</h3>' .
				'<p>' . $this->options['version'] . '&nbsp;(' . $this->options['smwgUpgradeKey'] . ')'. '</p>' .
				'<h3 class="section"><span class="title">' . Message::get( 'smw-upgrade-error-why-title' ) . '</span></h3>' .
				Message::get( 'smw-upgrade-error-why-explain', Message::PARSE ) .
				'<h3 class="section"><span class="title">' . Message::get( 'smw-upgrade-error-how-title' ) . '</span></h3>' .
				'<p>' . Message::get( 'smw-upgrade-error-how-explain-admin', Message::PARSE ) . '&nbsp;'.
				Message::get( 'smw-upgrade-error-how-explain-links', Message::PARSE ) . '</p>';

		$error = [
			'title' => Message::get( 'smw-upgrade-error-title' ),
			'content' => $content,
			'borderColor' => '#F44336'
		];

		return $error;
	}

	private function buildHTML( array $error ) {
		$logo = $this->options['wgScriptPath'] . '/resources/assets/mediawiki.png';
		$content = isset( $error['content'] ) ? $error['content'] : '';
		$title = isset( $error['title'] ) ? $error['title'] : '' ;
		$borderColor = isset( $error['borderColor'] ) ? $error['borderColor'] : '#fff';

		$output = <<<HTML
<!DOCTYPE html>
<html lang="en" dir="ltr">
	<head>
		<meta http-equiv="refresh" content="30" charset="UTF-8" />
		<title>{$title}</title>
		<style media='screen'>
			body {
				color: #000;
				background-color: #fff;
				font-family: sans-serif;
				padding: 0em;
			}
			img, h1, h2, ul  {
				text-align: left;
				margin: 0.1em 0 0.3em;
				margin-left:10px;
			}
			p, h2 {
				text-align: left;
				margin: 0.5em 0 1em;
				margin-left:10px;
			}
			.title {
				margin-left:10px;
			}
			h1 {
				font-size: 140%;
			}
			h2 {
				font-size: 110%;
			}
			h3 {
				font-size: 100%;
				margin-left:10px;
			}
			.progress-bar-animated {
				animation: progress-bar-stripes 2s linear infinite;
			}
			.progress-bar-striped {
				background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-size: 1rem 1rem;
			}
			.progress-bar-section {
				margin-right: 20px;margin-bottom:10px;margin-top:10px;white-space: nowrap;padding: 8px 0 8px 0;;
			}
			.progress-bar {
				background-color: #eee;transition: width .6s ease;justify-content: center;display: flex;white-space: nowrap;
			}
			.section {
				background-color: #eee; padding: 6px; margin-left: -8px; margin-right: -8px;
			}
			@keyframes progress-bar-stripes {
				from { background-position: 28px 0; } to { background-position: 0 0; }
			}
		</style>
	</head>
	<body>
		<div style="background-color:#eee;padding-bottom:2px; margin-top: -8px;margin-left: -8px;margin-right: -8px; border-bottom: 4px solid {$borderColor};">
		<img style="width:50px;margin-left:18px;" src="{$logo}" alt='The MediaWiki logo' />
		<h1 style="color:#222;margin-left:18px;">{$title}</h1></div>
		<div style="margin-top:10px;line-height:1.4em;">{$content}
		</div>
	</body>
</html>
HTML;

		return $output;
	}

	private function progress( $msg, $value ) {
		return <<<EOT
         <div class='progress-bar-section'>
         <div style='width:100%;margin-left: 8px;margin-bottom: 5px;'>$msg</div>
         <div class='progress-bar progress-bar-striped progress-bar-animated' style='background-color:#FFC107;height:16px;padding:2px;width:$value%;margin-left: 8px;'></div>
         </div>
EOT;
	}

}
