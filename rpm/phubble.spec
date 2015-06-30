%global github_owner     fkooman
%global github_name      phubble

Name:       phubble
Version:    0.1.0
Release:    1%{?dist}
Summary:    Simple Microblogging Platform for Communities

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    https://github.com/%{github_owner}/%{github_name}/archive/%{version}.tar.gz
Source1:    phubble-httpd.conf
Source2:    phubble-autoload.php

BuildArch:  noarch

Requires:   php >= 5.4
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires:   php-composer(fkooman/json) >= 0.6.0
Requires:   php-composer(fkooman/json) < 0.7.0
Requires:   php-composer(fkooman/ini) >= 0.2.0
Requires:   php-composer(fkooman/ini) < 0.3.0
Requires:   php-composer(fkooman/rest) >= 0.9.0
Requires:   php-composer(fkooman/rest) < 0.10.0
Requires:   php-composer(fkooman/rest-plugin-indieauth) >= 0.5.0
Requires:   php-composer(fkooman/rest-plugin-indieauth) < 0.6.0
Requires:   php-composer(fkooman/rest-plugin-bearer) >= 0.5.1
Requires:   php-composer(fkooman/rest-plugin-bearer) < 0.6.0
Requires:   php-pear(pear.twig-project.org/Twig) >= 1.15
Requires:   php-pear(pear.twig-project.org/Twig) < 2.0
Requires:   php-pear(htmlpurifier/htmlpurifier) >= 4.6.0
Requires:   php-pear(htmlpurifier/htmlpurifier) < 5

#Starting F21 we can use the composer dependency for Symfony
#Requires:   php-composer(symfony/classloader) >= 2.3.9
#Requires:   php-composer(symfony/classloader) < 3.0
Requires:   php-pear(pear.symfony.com/ClassLoader) >= 2.3.9
Requires:   php-pear(pear.symfony.com/ClassLoader) < 3.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
Phubble is a simple microblogging service for communities. It features group
spaces that can be made secret so they can only accessed by members of the 
group.

%prep
%setup -qn %{github_name}-%{version}

sed -i "s|dirname(__DIR__)|'%{_datadir}/phubble'|" bin/phubble-init-db

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/phubble.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/phubble
cp -pr web views src ${RPM_BUILD_ROOT}%{_datadir}/phubble

# use our own class loader
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/phubble/vendor
cp -pr %{SOURCE2} ${RPM_BUILD_ROOT}%{_datadir}/phubble/vendor/autoload.php

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/phubble
cp -p config/config.ini.default ${RPM_BUILD_ROOT}%{_sysconfdir}/phubble/config.ini
ln -s ../../../etc/phubble ${RPM_BUILD_ROOT}%{_datadir}/phubble/config

# Data
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/phubble

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/phubble(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/phubble || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/phubble(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/phubble.conf
%config(noreplace) %{_sysconfdir}/phubble
%{_bindir}/phubble-init-db
%dir %{_datadir}/phubble
%{_datadir}/phubble/src
%{_datadir}/phubble/vendor
%{_datadir}/phubble/web
%{_datadir}/phubble/views
%{_datadir}/phubble/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/phubble
%doc CHANGES.md README.md agpl-3.0.txt composer.json config/

%changelog
* Tue Jun 30 2015 François Kooman <fkooman@tuxed.net> - 0.1.0-1
- initial package
