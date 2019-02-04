'use strict';
define(['underscore', 'pim/mass-edit-form/product/operation', 'clickandmortar/template/add-rekognition-data'],
    function (_, BaseOperation, template) {
        return BaseOperation.extend({
            template: _.template(template),
        });
    }
);
