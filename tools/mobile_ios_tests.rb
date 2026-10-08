require 'xcodeproj'
require 'fileutils'
project_path = Dir['mobile/ios/*.xcodeproj'].first
project = Xcodeproj::Project.open(project_path)
application = project.targets.find { |target| target.product_type == 'com.apple.product-type.application' }
tests = project.new_target(:ui_test_bundle, 'MobileChecks', :ios, '15.1')
tests.add_dependency(application)
folder = File.join(File.dirname(project_path), 'MobileChecks')
FileUtils.mkdir_p(folder)
FileUtils.cp('tools/mobile_ios_tests.swift', File.join(folder, 'MobileChecks.swift'))
group = project.main_group.new_group('MobileChecks', 'MobileChecks')
tests.source_build_phase.add_file_reference(group.new_file('MobileChecks.swift'))
tests.build_configurations.each do |config|
  config.build_settings.merge!({
    'PRODUCT_BUNDLE_IDENTIFIER' => 'ru.choppro.guard.checks',
    'GENERATE_INFOPLIST_FILE' => 'YES',
    'SWIFT_VERSION' => '5.0',
    'TEST_TARGET_NAME' => application.name,
    'CODE_SIGN_IDENTITY' => '-',
    'CODE_SIGNING_REQUIRED' => 'NO',
    'CODE_SIGNING_ALLOWED' => 'YES',
    'TARGETED_DEVICE_FAMILY' => '1,2'
  })
end
project.save
scheme = Xcodeproj::XCScheme.new
scheme.configure_with_targets(application, tests, launch_target: application)
scheme.test_action.build_configuration = 'Release'
scheme.save_as(project_path, 'MobileChecks', true)
