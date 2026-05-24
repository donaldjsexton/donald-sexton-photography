<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Tenancy\SiteProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('tenant:create
    {name : Display name for the site}
    {subdomain : Subdomain to claim}
    {admin_email : First admin email}
    {--admin-name=Owner : First admin display name}
    {--password= : Admin password (prompted if omitted)}')]
#[Description('Provision a new tenant site with a first admin and starter content.')]
class CreateTenant extends Command
{
    public function handle(SiteProvisioner $provisioner): int
    {
        $password = (string) ($this->option('password') ?: $this->secret('Admin password'));

        $data = [
            'name' => (string) $this->argument('name'),
            'subdomain' => strtolower(trim((string) $this->argument('subdomain'))),
            'admin_name' => (string) $this->option('admin-name'),
            'admin_email' => (string) $this->argument('admin_email'),
            'admin_password' => $password,
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', 'unique:sites,subdomain'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (Site::isReservedSubdomain($data['subdomain'])) {
            $this->error('That subdomain is reserved.');

            return self::FAILURE;
        }

        $site = $provisioner->provision($data);

        $this->info(sprintf('Created site "%s" at %s (id %d).', $site->name, $site->subdomain, $site->id));

        return self::SUCCESS;
    }
}
