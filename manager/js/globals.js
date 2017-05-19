var customClass;
var ExternalModules = {};

ExternalModules.Settings = function(){}


ExternalModules.Settings.prototype.shouldShowSettingOnProjectManagementPage = function(setting, system) {
    if(!system){
        // Always show project level settings.
        return true;
    }
    if(setting.key == enabled){
        // Hide the 'enabled' setting on projects, since we have buttons for enabling/disabling now.
        // Also, leaving this setting in place caused the enabled flag to be changed from a boolean to a string (which could cause unexpected behavior).
        return false;
    }
    if(setting.overrideLevelValue == null && !ExternalModules.SUPER_USER){
        // Hide this setting since the override level will prevent the non-superuser from actually saving it.
        return false;
    }
    // Checking whether a system setting is actually overridden is necessary for the UI to reflect when
    // settings are overridden prior to allow-project-overrides being set to false.
    var alreadyOverridden = setting.value != setting.systemValue;
    return setting['allow-project-overrides'] || alreadyOverridden;
}

ExternalModules.Settings.prototype.getSettingRows = function(system, configSettings, savedSettings){
    var rowsHtml = '';
    var settingsObject = this;
    configSettings.forEach(function(setting){
        var setting = $.extend({}, setting);
        var saved = savedSettings[setting.key];
        var indexSubSet = 0;
        if (setting.sub_settings) {
            var i = 0;
            setting.sub_settings.forEach(function(subSetting) {
                if (savedSettings[subSetting.key]) {
                    setting.sub_settings[i].value = savedSettings[subSetting.key].value;
                    setting.sub_settings[i].systemValue =  savedSettings[subSetting.key].system_value;
                    //we keep the length of the array to know the number of elements
                    if(subSetting.value && Array.isArray(subSetting.value)){
                        indexSubSet = subSetting.value.length;
                    }
                }
                i++;
            });
        } else if(saved){
            setting.value = saved.value;
            setting.systemValue = saved.system_value;
        }
        // Will need to clean up because can't use PHP constants in .js file
        setting.overrideLevelKey = setting.key + ExternalModules.OVERRIDE_PERMISSION_LEVEL_SUFFIX;
        var overrideLevel = savedSettings[setting.overrideLevelKey];

        if(overrideLevel){
            setting.overrideLevelValue = overrideLevel.value
        }

        if(!pid){
            rowsHtml += '<tr>' + settingsObject.getSystemSettingColumns(setting) + '</tr>';
        }
        else if(settingsObject.shouldShowSettingOnProjectManagementPage(setting, system)){
            rowsHtml += settingsObject.getProjectSettingHTML(setting,system, indexSubSet,'', '');
        }
    });

    return rowsHtml;
}

ExternalModules.Settings.prototype.getSystemSettingColumns = function(setting){
    var columns = this.getSettingColumns(setting);

    if(setting['allow-project-overrides']){
        var overrideChoices = [
            { value: '', name: 'Superusers Only' },
            // Will need to clean up because can't use PHP constants in .js file
            { value: ExternalModules.OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS, name: 'Project Admins' },
        ];

        var selectAttributes = '';
        // Will need to clean up because can't use PHP constants in .js file
        if(setting.key == ExternalModules.KEY_ENABLED){
            // For now, we've decided that only super users can enable modules on projects.
            // To enforce this, we disable this override dropdown for ExternalModules::KEY_ENABLED.
            selectAttributes = 'disabled'
        }

        columns += '<td>' + this.getSelectElement(setting.overrideLevelKey, overrideChoices, setting.overrideLevelValue, selectAttributes) + '</td>';
    }
    else{
        columns += '<td></td>';
    }

    return columns;
};

ExternalModules.Settings.prototype.getProjectSettingHTML = function(setting, system, indexSubSet, rowsHtml, customClass){
    if(customClass != undefined){
        this.setCustomClass(customClass);
    }
    var rowTitleSubSetHtml = '';
    // SUB_SETTING
    if (setting.sub_settings) {
        if (setting.repeatable && (Object.prototype.toString.call(setting.value) === '[object Undefined]')) {

            if(indexSubSet == 0) {
                rowsHtml += '<tr class="'+customClass+'">' + this.getProjectSettingColumns(setting, system,'','') + '</tr>';
            }
        }

        for (var instance = 0; instance < indexSubSet; instance++) {
            //we add the sub_settings header
            if(indexSubSet == 0){
                //if values empty NEW form
                rowsHtml += '<tr class="'+customClass+'">' + this.getProjectSettingColumns(setting, system,'','') + '</tr>';
            }else{
                rowsHtml += '<tr class="'+customClass+'">' + this.getProjectSettingColumns(setting, system, instance, indexSubSet) + '</tr>';
            }

            var settingsObject = this;
            setting.sub_settings.forEach(function (subSetting) {
                subSetting.sub_setting = true;
                rowsHtml += '<tr class = "subsettings-table '+ customClass+'">' + settingsObject.getProjectSettingColumns(subSetting, system, instance,'') + '</tr>';
            });
        }
    } else if (setting.repeatable && (Object.prototype.toString.call(setting.value) === '[object Array]')) {
        for (var instance=0; instance < setting.value.length; instance++) {
            rowsHtml += '<tr class="'+customClass+'">' + this.getProjectSettingColumns(setting, system, instance,'') + '</tr>';
        }
    } else {
        rowsHtml += '<tr class="'+customClass+'">' + this.getProjectSettingColumns(setting, system,'','') + '</tr>';
    }

    return rowsHtml;
}

ExternalModules.Settings.prototype.getProjectSettingColumns = function(setting, system, instance, header){
    var setting = $.extend({}, setting);
    var projectName = setting['project-name'];
    if(projectName){
        setting.name = projectName;
    }
    var overrideButtonAttributes = 'data-system-value="' + this.getAttributeValueHtml(setting.systemValue) + '"';

    if(system && (setting.type == "checkbox")) {
        if (setting.value == "false") {
            setting.value = 0;
        }
        if (setting.systemValue == "false") {
            setting.systemValue = 0;
        }
    }
    if(system && (setting.value == setting.systemValue)){
        overrideButtonAttributes += " style='display: none;'";
    }

    var columns = this.getSettingColumns(setting, instance, header);

    if(system){
        columns += "<td style='width: 50px'><div style='min-height: 50px;'><button "+overrideButtonAttributes+" class='external-modules-use-system-setting'>Use<br>System<br>Setting</button></div></td>";
    }
    else{
        columns += '<td></td>';
    }

    return columns;
}

ExternalModules.Settings.prototype.getAttributeValueHtml = function(s){
    if(typeof s == 'string'){
        s = s.replace(/"/g, '&quot;');
        s = s.replace(/'/g, '&apos;');
    }

    if (typeof s == "undefined") {
        s = "";
    }

    return s;
}

ExternalModules.Settings.prototype.getSettingColumns = function(setting, instance, header){
    var type = setting.type;
    var key = setting.key;
    var value = setting.value;

    var instanceLabel = "";
    if (typeof instance != "undefined") {
        instanceLabel = (instance+1)+". ";
    }
    var html = "<td></td>";
    if(type != 'sub_settings') {
        html = "<td><span class='external-modules-instance-label'>" + instanceLabel + "</span><label>" + setting.name + ":</label></td>";
    }
    if (typeof instance != "undefined" && instance != "") {
        // for looping for repeatable elements
        if(header < 1 || typeof header == "undefined"){
            if (typeof value == "undefined") {
                value = "";
            } else {
                value = value[instance];
            }
        }
        key = this.getInstanceName(key, instance);
    }else if((setting.repeatable==true || setting.sub_setting) && (instance != "" || instance != "undefined") && (Object.prototype.toString.call(value) === '[object Array]')){
        //looping repeatable or sub_setting elements
        value = value[instance];
    }

    var inputHtml;
    if(type == 'dropdown'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'field-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'form-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'event-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'arm-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'user-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'user-role-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'dag-list'){
        inputHtml = this.getSelectElement(key, setting.choices, value, []);
    }
    else if(type == 'project-id'){
        inputHtml = "<div style='width:200px'>" + this.getSelectElement(key, setting.choices, value, {"class":"project_id_textbox"}) + "</div>";
    }
    else if(type == 'textarea'){
        inputHtml = this.getTextareaElement(key, value, {"rows" : "6","cols" : "45"});
    }
    else if(type == 'sub_settings'){
        inputHtml = "<span class='external-modules-instance-label'>"+instanceLabel+"</span><label name='"+key+"'>" + setting.name + ":</label>";
    }
    else if(type == 'radio'){
        inputHtml = "";
        for(var i in setting.choices ){
            var choice = setting.choices[i];

            var inputAttributes = [];
            if(choice.value == value) {
                inputAttributes["checked"] = "true";
            }

            inputHtml += this.getInputElement(type, key, choice.value, inputAttributes) + '<label>' + choice.name + '</label><br>';
        }
    } else {
        var inputAttributes = [];
        if(type == 'checkbox' && value == 1){
            inputAttributes['checked'] = 'checked';
        }
        // TODO Is this only triggered when a project is overriding the system value, but now allow-project-overrides is disabled?
        var alreadyOverridden = setting.value != setting.systemValue;
        if ((type == 'file') && (!setting['allow-project-overrides'] && alreadyOverridden) && setting['allow-project-overrides'] != undefined) {
            inputAttributes['disabled'] = "disabled";
        }

        inputHtml = this.getInputElement(type, key, value, inputAttributes);
    }

    html += "<td class='external-modules-input-td'>" + inputHtml + "</td>";

    html += this.addRepeatableButtons(setting, instance, header, type, key);

    return html;
}

ExternalModules.Settings.prototype.addRepeatableButtons = function(setting, instance, header, type, key){
    var html = '';
    // no repeatable files allowed
    if (setting.repeatable && (type != "file")) {
        // fill with + and - buttons and hide when appropriate
        // set original sign for first item when + is not displayed
        var addButtonStyle = " style='display: none;'";
        var removeButtonStyle = " style='display: none;'";
        var originalTagStyle = " style='display: none;'";


        if ((typeof setting.value == "undefined") ||  (typeof instance == "undefined") || (instance + 1 >=  setting.value.length)) {
            addButtonStyle = "";
        }

        if ((typeof instance != "undefined") && (instance > 0)) {
            removeButtonStyle = "";
        }

        if ((addButtonStyle == "") && (removeButtonStyle == "") && (typeof instance != "undefined") && (instance === 0)) {
            originalTagStyle = "";
        }

        //we are on the original element
        if(type == 'sub_settings' && (instance === 0) && header > 0){
            originalTagStyle = "";
            addButtonStyle = " style='display: none;'";
            removeButtonStyle = " style='display: none;'";
        }


        var settingsClass = '';
        if(type == 'sub_settings'){
            settingsClass = "-subsettings";
        }

        html += "<td class='external-modules-add-remove-column'>";
        html += "<button class='external-modules-add-instance"+settingsClass+"'" + addButtonStyle + ">+</button>";
        html += "<button class='external-modules-remove-instance"+settingsClass+"'"+ removeButtonStyle + ">-</button>";
        html += "<span class='external-modules-original-instance"+settingsClass+"'" + originalTagStyle + ">original</span>";
        html += "</td>";
    } else {
        html += "<td></td>";
    }
    //we add it after repeateable as it is a sub-setting and depends on it
    if(type == 'sub_settings' &&  (header < 1 || typeof header == "undefined")){
        html += this.getSubSettingsElements(key, setting.sub_settings, instance);
    }

    return html;
}

ExternalModules.Settings.prototype.getSubSettingsElements = function(name, value, instance){
    if (typeof value == "undefined") {
        value = "";
    }

    var html = '';
    for(var i=0; i<value.length;i++){
        html += '<tr class = "subsettings-table">'+this.getSettingColumns(value[i])+'<td></td></tr>';
    }
    return html;
}

ExternalModules.Settings.prototype.getSelectElement = function(name, choices, selectedValue, selectAttributes){
    if(!selectAttributes){
        selectAttributes = [];
    }

    var optionsHtml = '';
    optionsHtml += '<option value=""></option>';
    for(var i in choices ){
        var choice = choices[i];
        var value = choice.value;

        var optionAttributes = ''
        if(value == selectedValue){
            optionAttributes += 'selected'
        }

        optionsHtml += '<option value="' + this.getAttributeValueHtml(value) + '" ' + optionAttributes + '>' + choice.name + '</option>';
    }

    var defaultAttributes = {"class" : "external-modules-input-element"};
    var attributeString = this.getElementAttributes(defaultAttributes,selectAttributes);

    return '<select ' + attributeString + ' name="' + name + '" ' + selectAttributes + '>' + optionsHtml + '</select>';
}

ExternalModules.Settings.prototype.getInputElement = function(type, name, value, inputAttributes){
    if (typeof value == "undefined") {
        value = "";
    }
    if (type == "file") {
        if (pid) {
            return this.getProjectFileFieldElement(name, value, inputAttributes);
        } else {
            return this.getSystemFileFieldElement(name, value, inputAttributes);
        }
    } else {
        return '<input type="' + type + '" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" ' + this.getElementAttributes({"class":"external-modules-input-element"},inputAttributes) + '>';
    }
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getSystemFileFieldElement = function(name, value, inputAttributes) {
    return this.getFileFieldElement(name, value, inputAttributes, "");
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getProjectFileFieldElement = function(name, value, inputAttributes) {
    return this.getFileFieldElement(name, value, inputAttributes, "pid=" + pid);
}

// abstracted because file fields need to be reset in multiple places
ExternalModules.Settings.prototype.getFileFieldElement = function(name, value, inputAttributes, pid) {
    var attributeString = this.getElementAttributes([],inputAttributes);
    var type = "file";
    if ((typeof value != "undefined") && (value !== "")) {
        var html = '<input type="hidden" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" >';
        html += '<span class="external-modules-edoc-file"></span>';
        html += '<button class="external-modules-delete-file" '+attributeString+'>Delete File</button>';
        $.post('ajax/get-edoc-name.php?' + pid, { edoc : value }, function(data) {
            $("[name='"+name+"']").closest("tr").find(".external-modules-edoc-file").html("<b>" + data.doc_name + "</b><br>");
        });
        return html;
    } else {
        attributeString = this.getElementAttributes({"class":"external-modules-input-element"},inputAttributes);
        return '<input type="' + type + '" name="' + name + '" value="' + this.getAttributeValueHtml(value) + '" ' + attributeString + '>';
    }
}

ExternalModules.Settings.prototype.getTextareaElement = function(name, value, inputAttributes){
    if (typeof value == "undefined") {
        value = "";
    }

    return '<textarea  id="' + name + '" contenteditable="true" name="' + name + '" ' + this.getElementAttributes([],inputAttributes) + '>'+this.getAttributeValueHtml(value)+'</textarea>';

}

ExternalModules.Settings.prototype.getElementAttributes = function(defaultAttributes, additionalAttributes) {
    var attributeString = "";

    for (var tag in additionalAttributes) {
        if(defaultAttributes[tag]) {
            attributeString += tag + '="' + defaultAttributes[tag] + ' ' + additionalAttributes[tag] + '" ';
            delete defaultAttributes[tag];
        }
        else {
            attributeString += tag + '="' + additionalAttributes[tag] + '" ';
        }
    }

    for (var tag in defaultAttributes) {
        attributeString += tag + '="' + defaultAttributes[tag] + '" ';
    }

    return attributeString;
}

ExternalModules.Settings.prototype.getCustomClass = function(){
    return customClass;
}
ExternalModules.Settings.prototype.setCustomClass = function(newcustomClass){
    customClass = newcustomClass;
}

ExternalModules.Settings.prototype.getInstanceName = function(name,instance){
    if(instance != 0){
        name += this.getInstanceSymbol()+instance;
    }
    return name;
}

ExternalModules.Settings.prototype.getInstanceSymbol = function(){
    return "____";
}


$(function(){
    // var getSettings = new ExternalModules.Settings();
    function getSettings(){
        return new ExternalModules.Settings();
    }

    /**
     * Function that given a position, returns the element name
     * @param positionElement
     * @returns {*}
     */
    function getOldName(positionElement){
        var oldName = positionElement.find('input').attr('name');
        if (!oldName) {
            oldName = positionElement.find('select').attr('name');
        }
        if (!oldName) {
            oldName = positionElement.find('textarea').attr('name');
        }
        return oldName;
    }

    /**
     * Function that given a name returns the name modified
     * @param oldName
     * @returns {string}
     */
    function getNewName(oldName){
        var idx = 1;
        var newName = getSettings().getInstanceName(oldName, idx);  // default: guess that this is the second variable
        var ary;
        if (ary = oldName.match(new RegExp(getSettings().getInstanceSymbol()+"(\\d+)$"))) {
            // transfer number (old + 1)
            idx = Number(ary[1]) + 1;
            newName = oldName.replace(getSettings().getInstanceSymbol() + ary[1], getSettings().getInstanceSymbol() + idx);
        }
        setIdx(idx);
        return newName;
    }
    /**
     * Set/Get of the element index when creating the new name
     * @type {number}
     */
    var idx_g = 1;
    function setIdx(idx){
        idx_g = idx;
    }
    function getIdx() {
        return idx_g;
    }

    var onValueChange = function() {
        var val;
        if ($(this).type == "checkbox") {
            val = $(this).is(":checked");
        } else {
            val = $(this).val();
        }
        var overrideButton = $(this).closest('tr').find('button.external-modules-use-system-setting');
        if (overrideButton) {
            var systemValue = overrideButton.data('system-value');
            if (typeof systemValue != "undefined") {
                if (systemValue == val) {
                    overrideButton.hide();
                } else {
                    overrideButton.show();
                }
            }
        }
    };

    $('#external-modules-configure-modal').on('change', '.external-modules-input-element', onValueChange);
    $('#external-modules-configure-modal').on('check', '.external-modules-input-element', onValueChange);

    /**
     * Function to add new elements
     */
    $('#external-modules-configure-modal').on('click', '.external-modules-add-instance-subsettings, .external-modules-add-instance', function(){
        // RULE: first variable is base name (e.g., survey_name)
        // second and following variables are base name + ____X, where X is a 0-based name
        // so survey_name____1 is the second variable; survey_name____2 is the third variable; etc.
        // RULE 2: Cannot remove first item
        var newInstanceTotal = "";
        var newclass = "";
        var oldName = "";
        if($(this).hasClass('external-modules-add-instance-subsettings')) {
            $(this).closest('tr').nextAll('tr.subsettings-table').each(function () {
                oldName = getOldName($(this).find('td:nth-child(2)'));
                var newName = getNewName(oldName);
                var idx = getIdx();

                //we copy the info
                var $newInstance = $(this).clone();

                // rename new instance of input/select and set value to empty string
                $newInstance.find('[name="' + oldName + '"]').attr('name', newName);
                $newInstance.find('[name="' + newName + '"]').val('');

                // rename label
                $newInstance.closest("tr").find('span.external-modules-instance-label').html((idx + 1) + ". ");
                $(this).closest("tr").find('span.external-modules-instance-label').html((idx) + ". ");

                newInstanceTotal += '<tr class = "subsettings-table '+getSettings().getCustomClass()+'">' + $newInstance.html() + '</tr>';
            });
            oldName = $(this).closest('tr').find('label').attr('name');
            newclass = "-subsettings";
        }else if($(this).hasClass('external-modules-add-instance')) {
            oldName = getOldName($(this).closest('tr'));
        }

        // show original sign if previous was first item
        if (!oldName.match(new RegExp(getSettings().getInstanceSymbol()))) {
            $("[name='"+oldName+"']").closest("tr").find(".external-modules-original-instance"+newclass).show();
        }

        //We show which one is the original
//		      $(this).closest("tr").find(".external-modules-original-instance"+newclass).show();

        var newName = getNewName(oldName);
        var idx = getIdx();

        var $newInstanceTitle = $(this).closest('tr').clone();
        $newInstanceTitle.find(".external-modules-remove-instance"+newclass).show();
        $newInstanceTitle.find(".external-modules-original-instance"+newclass).hide();
        $newInstanceTitle.find('[name="'+oldName+'"]').attr('name', newName);
        $newInstanceTitle.find('[name="'+newName+'"]').val('');
        $newInstanceTitle.find('span.external-modules-instance-label').html((idx+1)+". ");

        //We add the whole new block at the end
        if($(this).hasClass('external-modules-add-instance-subsettings')) {
            $(this).closest('tr').nextAll('tr.subsettings-table').last().after("<tr class = '"+getSettings().getCustomClass()+"'>"+$newInstanceTitle.html()+"</tr>"+newInstanceTotal);
        }else if($(this).hasClass('external-modules-add-instance')) {
            $newInstanceTitle.insertAfter($(this).closest('tr'));
        }

        // rename new instance of input/select and set value to empty string
        $newInstanceTitle.find('[name="'+oldName+'"]').attr('name', newName);
        $newInstanceTitle.find('[name="'+newName+'"]').val('');

        // rename label
        $(this).closest("tr").find('span.external-modules-instance-label').html((idx)+". ");

        // show only last +
        $(this).hide();
    });
    /**
     * Function that given a name returns removes the elements
     * @param oldName
     * @param newclass
     * @returns {string}
     */
    function removeElements(newclass,oldName){
        var oldNameParts = oldName.split(new RegExp(getSettings().getInstanceSymbol()));
        var baseName = oldNameParts[0];
        var i = 1;
        var j = 1;
        while ($("[name='" +  getSettings().getInstanceName(baseName,i)+ "']").length) {
            if (i == oldNameParts[1]) {
                // remove tr
                $("[name='" + getSettings().getInstanceName(baseName,i)+ "']").closest('tr').remove();
            } else {
                // rename label
                $("[name='" +  getSettings().getInstanceName(baseName,i) + "']").closest("tr").find('span.external-modules-instance-label').html((j + 1) + ". ");
                // rename tr: i --> j
                $("[name='" +  getSettings().getInstanceName(baseName,i)+ "']").attr('name',  getSettings().getInstanceName(baseName,j));
                j++;
            }
            i++;
        }
        if (j > 1) {
            $("[name='" + getSettings().getInstanceName(baseName,(j-1))+ "']").closest("tr").find(".external-modules-add-instance"+newclass).show();
        } else {
            $("[name='" + baseName + "']").closest("tr").find(".external-modules-add-instance"+newclass).show();
            $("[name='" + baseName + "']").closest("tr").find(".external-modules-original-instance"+newclass).hide();
        }
        return j;
    }

    /**
     * function to remove the elements
     */
    $('#external-modules-configure-modal').on('click', '.external-modules-remove-instance-subsettings, .external-modules-remove-instance', function(){
        // see RULE on external-modules-add-instance
        // we must maintain said RULE here
        // RULE 2: Cannot remove first item

        var newInstanceTotal = "";
        var index = 0;
        var newclass = "";
        if($(this).hasClass('external-modules-remove-instance-subsettings')) {
            $(this).closest('tr').nextAll('tr.subsettings-table').each(function () {
                newclass = "-subsettings";
                var oldName = getOldName($(this).find('td:nth-child(2)'));
                index = removeElements(newclass,oldName);
            });

            //we remove the 'parent' element
            var oldNameParts = $(this).closest('tr').find('label').attr('name').split(new RegExp(getSettings().getInstanceSymbol()));
            var baseName = oldNameParts[0];
            if (index > 1) {
                $("[name='"+ getSettings().getInstanceName(baseName,(index-1))+"']").closest("tr").find(".external-modules-add-instance-subsettings").show();
                $("[name='"+getSettings().getInstanceName(baseName,(index-1))+"']").closest("tr").find(".external-modules-original-instance-subsettings").hide();
            } else {
                $("[name='"+baseName+"']").closest("tr").find(".external-modules-add-instance-subsettings").show();
                $("[name='"+baseName+"']").closest("tr").find(".external-modules-original-instance-subsettings").hide();
            }

        }else if($(this).hasClass('external-modules-remove-instance')) {
            var oldName = getOldName($(this).closest('tr'));
            index = removeElements(newclass,oldName);
        }

        $(this).closest('tr').remove();
    });
});