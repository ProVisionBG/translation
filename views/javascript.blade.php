<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.16/angular.min.js"></script>
<script src="//johannesjo.github.io/angular-promise-buttons/angular-promise-buttons.min.js"></script>
<script>
    'use strict';

    jQuery.noConflict();

    var app = angular.module('trans', ['angularPromiseButtons'], function ($interpolateProvider) {
        $interpolateProvider.startSymbol('[[');
        $interpolateProvider.endSymbol(']]');
    });

    app.controller('Translations', ['$scope', '$http', function ($scope, $http) {
        $scope.setMessage = function (c, t) {
            $scope.message = {
                'type': t,
                'text': c
            };
        };

        $scope.clear = function () {
            $scope.items = [];
        };

        $scope.translateResult = {'total': 0, 'loading': 0, 'errors': 0, 'skip': 0, 'success': 0};
        $scope.translateAll = function () {
            $scope.translateResult = {'total': 0, 'loading': 0, 'errors': 0, 'skip': 0, 'success': 0};
            $scope.translateResult.total = $scope.items.length;
            var requests = 0;

            for (var key in $scope.items) {
                $scope.translateResult.loading++;
                if ($scope.items[key].translation === null || $scope.items[key].translation === '') {
                    requests++;
                    setTimeout(function (key) {
                        $scope.translate($scope.items[key].value, $scope.items[key].name, function (data) {
                            $scope.translateResult.loading--;
                            $scope.translateResult.success++;
                            for (var i in $scope.items) {
                                if ($scope.items[i].name === data.key) {
                                    $scope.items[i].translation = data.text;
                                    $scope.items[i].check = true;
                                }
                            }
                        }, function () {
                            $scope.translateResult.loading--;
                            $scope.translateResult.errors++;
                        });
                    }, 500 * requests, key);
                } else {
                    $scope.translateResult.loading--;
                    $scope.translateResult.skip++;
                }
            }
        };

        $scope.translate = function (text, key, success, error) {
            $http.post("{{ Administration::route('translations.translate') }}", {
                'key': key,
                'origin': $scope.currentLocale,
                'target': $scope.currentEditable,
                'text': text
            }).success(success)
                .error(error);
        };

        $scope.delete = function ($index) {
            $http.post("{{ Administration::route('translations.delete') }}", {
                'name': $scope.items[$index].name
            }).success(function () {
                $scope.items.splice($index, 1);
            });
        };

        $scope.fetch = function () {
            var group = JSON.parse($scope.currentGroup);
            $http.post("{{ Administration::route('translations.items') }}", {
                'group': group.group,
                'vendor': group.vendor,
                'package': group.package,

                'locale': $scope.currentLocale,
                'translate': $scope.currentEditable
            }).success(function (data) {
                $scope.items = data;
            })
                .error(function (data, status, headers, config) {
                    $scope.setMessage(status, 'danger');
                });
        };

        $scope.store = function ($index) {
            var group = JSON.parse($scope.currentGroup);
            $http.post("{{ Administration::route('translations.store') }}", {
                'name': $scope.items[$index].name,
                'value': $scope.items[$index].translation,
                'locale': $scope.currentEditable,
                'group': group.group,
                'vendor': group.vendor,
                'package': group.package
            })
                .error(function (data, status, headers, config) {
                    $scope.setMessage(status, 'danger');
                });
            $scope.items[$index].check = false;
        };

        $scope.fetchCommand = function ($index) {
            return $http.post("{{ Administration::route('translations.fetch-command') }}")
                .success(function () {
                    $scope.getGroups();
                })
                .error(function (data, status, headers, config) {
                    $scope.setMessage(status, 'danger');
                });
        };

        $scope.dumpCommand = function ($index) {
            return $http.post("{{ Administration::route('translations.dump-command') }}")
                .error(function (data, status, headers, config) {
                    $scope.setMessage(status, 'danger');
                });
        };

        $scope.locales = [];
        $scope.groups = [];
        $scope.currentLocale = null;
        $scope.currentGroup = null;
        $scope.currentEditable = null;
        $scope.items = [];
        $scope.message = null;
        $scope.showEmptyOnly = false;

        $http.get("{{ Administration::route('translations.locales') }}").success(function (data) {
            $scope.locales = data;
        }).error(function (data, status, headers, config) {
            $scope.setMessage(status, 'danger');
        });

        $scope.getGroups = function () {
            $http.get("{{ Administration::route('translations.groups') }}").success(function (data) {
                $scope.groups = data;
            }).error(function (data, status, headers, config) {
                $scope.setMessage(status, 'danger');
            });
        }
        $scope.getGroups();

    }])
</script>