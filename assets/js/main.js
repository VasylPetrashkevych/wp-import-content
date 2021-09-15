;(function () {
    const route = '/wp-json/wp-import-content/v1'

    function show_loader(status = true) {
        const loader = document.getElementById('loader');
        loader.classList.toggle('hidden')
    }

    const cache = {
        fileID: '',
        fields: [],
        postType: '',
        postId: undefined,
        groupId: undefined,
        groupValueID: undefined,
    }
    const status = {
        source: true,
        target: true
    }
    //load file
    {
        document.querySelector('[data-action="load-file"]').addEventListener('click', function (e) {
            e.preventDefault();
            show_loader();
            const file = wp.media({
                title: 'Upload file',
                multiple: false,
                button: {
                    text: 'Use this file'
                },
                library: {
                    type: [
                        'text/csv'
                    ]
                },

            }).open()
                .on('select', function () {
                    const uploaded_file = file.state().get('selection').first();
                    const fileData = uploaded_file.toJSON();
                    const fileDataHtml = document.querySelector('[data-file]');
                    fileDataHtml.innerHTML = fileData.filename;
                    fileDataHtml.setAttribute('data-file', fileData.id);
                    cache.fileID = fileData.id;
                    fetch(`${route}/read-file/?id=${fileData.id}`)
                        .then(response => response.json())
                        .then(data => {
                            document.querySelector('[data-fields="csv"] .list').innerHTML = data;
                            status.target = true;
                            if (status.source && status.target) {
                                startMapping();
                            }
                            show_loader();
                        })
                        .catch(error => {
                            console.log(error);
                        })
                });
        })
    }
    //load custom fields
    {
        function add_options(data) {
            const field = document.querySelector('#select_post_tr');
            field.style.display = 'table-row';
            const content = field.querySelector('#select_post');
            content.innerHTML = data;
        }

        document.querySelector('#select_post_type').addEventListener('change', function () {
            show_loader();
            fetch(`${route}/load-custom-fields/?post_type=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    cache.postType = this.value;
                    add_options(data.posts);
                    document.querySelector('[data-fields="custom"] .list').innerHTML = data.html;
                    status.source = true;
                    if (status.source && status.target) {
                        startMapping();
                    }
                    show_loader();
                })
                .catch(error => {
                    console.log(error);
                })
        });

        document.addEventListener('change', function (e) {

            const group_field_selector = document.querySelector('#select_post_acf_group');
            const value_of_group_field = document.querySelector('#select_post_acf_group_value')
            if (e.target === document.getElementById('select_post')) {
                show_loader();
                fetch(`${route}/load-custom-fields/?post_type=${cache.postType}&post_id=${e.target.value}`)
                    .then(response => response.json())
                    .then(data => {
                        cache.postId = e.target.value;
                        document.querySelector('[data-fields="custom"] .list').innerHTML = data.html;
                        document.querySelector('#select_post_acf_group_row').style.display = 'table-row';
                        group_field_selector.innerHTML = data.group_html;
                        status.source = true;
                        if (status.source && status.target) {
                            startMapping();
                        }
                        show_loader();
                    })
                    .catch(error => {
                        console.log(error);
                    })
            }
            if (e.target === group_field_selector) {
                show_loader();
                const groupFields = document.querySelectorAll('[data-fields="custom"] .list [data-group]');
                const groupFieldValue = document.querySelector('#select_post_acf_group');
                groupFields.forEach(groupField => {
                    groupField.style.display = groupField.dataset.group === groupFieldValue.value ? 'block' : 'none';
                });
                cache.groupID = groupFieldValue.value;
                fetch(`${route}/load-group-fields/?post_id=${cache.postId}&group_id=${groupFieldValue.value}`)
                    .then(response => response.json())
                    .then(data => {
                        cache.groupValueID = groupFieldValue.value;
                        document.querySelector('#select_post_acf_group_value_row').style.display = 'table-row'
                        value_of_group_field.innerHTML = data.fields;
                        show_loader();
                    })
                    .catch(error => {
                        console.log(error);
                    })
            }

            if(e.target === value_of_group_field) {
                show_loader();
                cache.groupValueSlug = value_of_group_field.value;
                fetch(`${route}/get-field-data/?field_id=${value_of_group_field.value}&group_id=${cache.groupID}&post_id=${cache.postId}`)
                    .then(response=>response.json())
                    .then(data => {
                        document.querySelector('[data-fields="custom"] .list').innerHTML = data.html;
                        startMapping();
                        show_loader();
                    })
            }
        })
    }
    //Start import
    {
        const button = document.querySelector('[data-button-action="start-import"]');
        button.addEventListener('click', function (e) {
            e.preventDefault();
            show_loader();
            const result = {
                type: 'posts',
                post_type: 'page',
                mapped: {},
                data: {...cache},
            };
            const groups = document.querySelectorAll('.field_group');
            groups.forEach(group => {
                const fields = group.querySelectorAll('.target-data-field.mapped');
                fields.forEach(field => {
                    const fieldData = field.dataset;
                    result.mapped[fieldData.associate] = {
                        target: fieldData.field,
                        type: fieldData.type,
                        targetKey: fieldData.fieldKey,
                    }
                })

            })

            fetch(`${route}/start-import/`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(result),
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    show_loader();
                })
                .catch(error => {
                    console.log(error);
                })
        })
    }
    //data association with drag and drop
    {
        let target = undefined;
        let selected = undefined;

        function startMapping() {
            const sourceFields = document.querySelectorAll('.file-data li');
            const targetFields = document.querySelectorAll('.target-data li');

            sourceFields.forEach(field => {
                field.addEventListener('dragstart', sourceDragStart);
                field.addEventListener('dragend', sourceDragEnd);
            })
            targetFields.forEach(field => {
                field.addEventListener('dragenter', targetDragEnter);
                field.addEventListener('dragover', targetDragOver);
                field.addEventListener('drop', targetDrop);
                field.addEventListener('dragleave', targetLeave);

            })

            function sourceDragStart(e) {
                target = this;
                this.classList.add('selected')
            }

            function sourceDragEnd(e) {
                e.preventDefault();
            }

            function targetDragEnter(e) {
                e.preventDefault();
            }

            function targetDrop(e) {
                const element = selected.querySelector('.target-data-field');
                element.innerHTML = target.innerHTML;
                element.setAttribute('data-associate', target.dataset.field)
                selected.classList.remove('drag-over');
                element.classList.add('mapped');
                if (selected !== undefined) {
                    target.remove();
                }
            }

            function targetDragOver(e) {
                selected = this;
                this.classList.add('drag-over');
                e.preventDefault();
            }

            function targetLeave(e) {
                selected = undefined;
                this.classList.remove('drag-over');
            }
        }
    }
})();