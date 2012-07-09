<?php
/**
 * @package    SdsAuthModule
 * @license    MIT
 */
namespace SdsAuthModule;

/**
 * Event name strings
 *
 * @license MIT
 * @link    http://www.doctrine-project.org/
 * @since   0.1.0
 * @author  Tim Roediger <superdweebie@gmail.com>
 */
class Events
{
    const identifier = 'SdsAuthModule';

    const login = 'login';

    const logout = 'logout';

    const register = 'register';

    const recoverPassword = 'recoverPassword';
}