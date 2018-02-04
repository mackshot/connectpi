<?php

switch ($_POST['mode']) {
    case 'reboot':
	shell_exec("sudo reboot");
	break;
    case 'shutdown':
	shell_exec("sudo shutdown -h now");
	break;
}