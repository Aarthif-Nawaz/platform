<?php

namespace Ushahidi\App\ImportUshahidiV2\Mappers;

use Ushahidi\Core\Entity;
use Ushahidi\Core\Entity\User;

class UserMapper
{
    protected $roleMap = [
        'superadmin' => 'admin',
        'admin'      => 'admin',
        'login'      => 'user',
        'member'     => 'user'
    ];

    public function __invoke(array $input) : Entity
    {
        return new User([
            'email' => $input['email'],
            'realname' => $input['name'] ?? '',
            'role' => $this->getRole($input['role']),
        ]);
    }

    protected function getRole($role)
    {
        $roles = explode(',', $role);

        return collect($roles)->reduce(function ($c, $item) {
            // If we already found an admin role
            if ($c === 'admin') {
                return 'admin';
            }

            // If role maps to admin
            if (isset($this->roleMap[$item]) && $this->roleMap[$item] === 'admin') {
                // Set v3 role to admin
                return 'admin';
            }

            // Otherwise map all other roles to user
            return 'user';
        }, 'user');
    }
}
