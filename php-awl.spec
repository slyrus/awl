%define snapshot 0 
%define gitrev 0
%if %{snapshot}
%define snapshotversionstring .%{gitrev}git
%define snapshotpackagestring -git%{gitrev}
%endif

%define realname awl
Name: php-%{realname}
Summary: Andrew's Web Libraries - PHP Utility Libraries
Version: 0.54
Release: 9%{?snapshotversionstring}%{?dist}
Group: Development/Libraries
License: GPL
Source: http://debian.mcmillan.net.nz/packages/awl/awl-%{version}%{?snapshotpackagestring}.tar.gz
URL: http://andrew.mcmillan.net.nz/projects/awl
BuildArch: noarch
 
%description
This package contains Andrew's Web Libraries.  This is a set                            
of hopefully lightweight libraries for handling a variety of
useful things for web programming, including:
 - Session management
 - User management
 - DB Records
 - Simple reporting
 - DB Schema Updating
 - iCalendar parsing
 
%prep
%setup -q -n "%{realname}-%{version}%{?snapshotpackagestring}"
 
%build
make
 
%install
rm inc/AWLUtilities.php.in
mkdir -p %{buildroot}/%{_datadir}/php/%{realname}
cp -a dba inc %{buildroot}/%{_datadir}/php/%{realname}
 
%files
%defattr(-,root,root)
%{_datadir}/php/%{realname}
%doc README
 
%changelog
* Tue Feb 22 2011 Felix Möller <mail@felixmoeller.de> - 0.46
- Initial version of AWL package
