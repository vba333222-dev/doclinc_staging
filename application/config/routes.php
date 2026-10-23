<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'home';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
$route['clinical-suggestions'] = 'clinical_suggestions/search';
$route['chat/attachment/(:num)'] = 'chat/chat/attachment/$1';
$route['profile/photo/update'] = 'profile_media/update_photo';
$route['profile/photo/(:num)'] = 'profile_media/photo/$1';
$route['profile/requirements'] = 'profile_requirements/status';
$route['profile/complete'] = 'profile_completion/index';
$route['profile/update'] = 'home/update_profile';
$route['location/address'] = 'location_address/address';
$route['puskesmas/operations'] = 'home_nakes/operations';
$route['puskesmas/operations/snapshot'] = 'puskesmas_operations/snapshot';
$route['puskesmas/operations/medical-record/(:num)'] = 'puskesmas_operations/medical_record/$1';
$route['home_nakes/clinical_workflow/(:num)'] = 'home_nakes/clinical_workflow/$1';
$route['home/clinical_workflow/(:num)'] = 'home/clinical_workflow/$1';
$route['puskesmas/operations/clinical-workflow/(:num)'] = 'puskesmas_operations/clinical_workflow/$1';
$route['clinical/review'] = 'home_nakes/clinical_review';
$route['clinical/disposition'] = 'home_nakes/clinical_disposition';
$route['clinical/disposition/revise'] = 'home_nakes/clinical_disposition_revise';
$route['clinical/record'] = 'home_nakes/clinical_record';
$route['clinical/finalize'] = 'home_nakes/clinical_finalize';
$route['clinical/amend'] = 'home_nakes/clinical_amend';
$route['clinical/close'] = 'home_nakes/clinical_close';
$route['konsultasi_nakes/visit_result_draft'] = 'konsultasi_nakes/visit_result_draft';
$route['konsultasi_nakes/visit_result_submit'] = 'konsultasi_nakes/visit_result_submit';
$route['konsultasi_nakes/visit_result_correction'] = 'konsultasi_nakes/visit_result_correction';
$route['realtime/connection-token'] = 'realtime_access/connection_token';
$route['realtime/subscription-token'] = 'realtime_access/subscription_token';
$route['notifications/snapshot'] = 'notifikasi/snapshot';
$route['realtime/requests/snapshot'] = 'realtime_requests/snapshot';
$route['login/change-password'] = 'login_nakes/change_password';
$route['logout'] = 'login/logout';
$route['login/change-password/submit'] = 'login_nakes/update_password';
$route['login/activate'] = 'login_nakes/activate';
$route['login/activate/auth'] = 'login_nakes/activate_auth';

/*
 * Permukaan masuk & verifikasi akun Nakes.
 *
 * Rute `login/activate*` dan `login/change-password*` di atas adalah alias lama:
 * keduanya kini dilayani modul `login_nakes` supaya hanya ada satu implementasi.
 * Alamat kanonik yang dipakai halaman nakes ada di bawah ini.
 */
$route['login-nakes'] = 'login_nakes/index';
$route['login-nakes/auth'] = 'login_nakes/auth';
$route['login-nakes/logout'] = 'login_nakes/logout';
$route['login-nakes/activate'] = 'login_nakes/activate';
$route['login-nakes/activate/auth'] = 'login_nakes/activate_auth';
$route['login-nakes/change-password'] = 'login_nakes/change_password';
$route['login-nakes/change-password/submit'] = 'login_nakes/update_password';
