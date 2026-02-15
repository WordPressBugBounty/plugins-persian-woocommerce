//variables
const pwBaseUrl = PersianWooCommerce.root; //'https://data.woodemo.ir/wp-json/'
const pwAssetsFolder = PersianWooCommerce.assetsFolder;
const pwNotyf = new Notyf({
    duration: 3000,
    position: {
        x: 'right',
        y: 'bottom',
    },
    dismissible: true,
    types: [
        {
            type: 'warning',
            background: '#ffc107',
        },
        {
            type: 'error',
            icon: false
        },
        {
            type: 'success',
            icon: false
        }
    ]
});

//functions
function pwLoadTippyInPage(){
    try {
        tippy('.tooltip-btn', {
            theme: 'tomato',
            content: (reference) => reference.getAttribute('tooltip-text')
        });
    }catch (error){
        console.error('Error: ', error);
    }
}
    
function pwGetVisiblePages(pagination) {

    let visiblePages = [];

    // Add first page if not already included in the range of current page
    if (pagination.currentPage > 3) {
        visiblePages.push(1);
        if (pagination.currentPage > 4) {
            visiblePages.push('...');
        }
    }

    // Add two pages before and after current page
    let start = Math.max(1, pagination.currentPage -2);
    let end = Math.min(pagination.totalPage,pagination.currentPage +2);


    for (let i=start; i<=end; i++) {
        visiblePages.push(i);

    }

    // Add last page if not already included in the range of current page
    if (pagination.totalPage > end ) {
        if (end < pagination.totalPage -2) {
            visiblePages.push('...');
        }
        visiblePages.push(pagination.totalPage);
    }

    return visiblePages;

}

function pwFormatPrice(number) {
    if(typeof(number) === "undefined" || number === null){
        return 0;
    }
    number = Math.trunc(number);
    let val = number.toString().replace(/,/g, '');
    val = val.replace(/\D/g, '');
    return new Intl.NumberFormat('en-US').format(val);
}

function pwFormatDate(timestamp, format){
    const date = new persianDate(timestamp);
    return format ? date.format(format) : date.format();
}

function pwDateToTimestamp(newDate){
    const array = newDate.split('-').map(item => Number(item));
    const date = new persianDate(array);
    return date.unix() * 1000;
}

function pwCheckDateFormatIsValid(str) {
    const pattern = /^\d{4}-\d{2}-\d{2}$/;
    return pattern.test(str);
}

function pwConvertPersianNumberToEnglish(number) {
    const persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

    return number.replace(/[۰-۹]/g, d => persianDigits.indexOf(d));
}

function pwParseQueryString(url) {
    const obj = {};
    const queryString = url.includes('?') ? url.split('?')[1] : url;

    const params = new URLSearchParams(queryString);

    for (const [key, value] of params.entries()) {
        const arrayMatch = key.match(/^([^\[]+)\[\d*\]$/);

        if (arrayMatch) {
            const baseKey = arrayMatch[1];
            if (!obj[baseKey]) obj[baseKey] = [];
            obj[baseKey].push(value);
        } else {
            if (obj[key] !== undefined) {
                if (!Array.isArray(obj[key])) {
                    obj[key] = [obj[key]];
                }
                obj[key].push(value);
            } else {
                obj[key] = value;
            }
        }
    }

    return obj;
}

function pwBuildQueryString(obj) {
    const params = [];

    for (const key in obj) {
        const value = obj[key];

        if (Array.isArray(value)) {
            value.forEach((item, index) => {
                params.push(`${encodeURIComponent(key)}[${index}]=${encodeURIComponent(item)}`);
            });
        } else if (value !== undefined && value !== null) {
            params.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
    }

    return params.join('&');
}

function pwSetUrlQueryParams(namePage, filters){
    delete filters.per_page;

    if(namePage){
        filters.page = namePage;
    }

    if(filters.from_date){
        filters.from_date = pwConvertPersianNumberToEnglish(pwFormatDate(filters.from_date * 1000, 'YYYY-MM-DD'));
    }
    if(filters.to_date){
        filters.to_date =  pwConvertPersianNumberToEnglish(pwFormatDate(filters.to_date * 1000, 'YYYY-MM-DD'));
    }

    window.history.replaceState(null, null,  '?' + pwBuildQueryString(filters));
}

function pwGenerateFiltersObject(filters){
    const objFilters = {};

    for (const objKey in filters) {
        if(filters[objKey]){
            objFilters[objKey] = filters[objKey]
        }
    }

    if(objFilters.from_date){
        objFilters.from_date = objFilters.from_date / 1000;
    }
    if( objFilters.to_date){
        objFilters.to_date =  objFilters.to_date / 1000;
    }

    return objFilters;
}

async function pwApiRequest(url, options = {}) {

    const {
        method = 'GET',
        data = {},
        headers = {},
        form = false
    } = options;

    try {
        const fetchOptions = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': PersianWooCommerce.nonce,
                ...headers,
            },
        };

        if (data && method !== 'GET') {
            if (form) {
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                const params = new URLSearchParams();
                for (const key in data) {
                    const value = data[key];
                    if (Array.isArray(value)) {
                        value.forEach(item => {
                            params.append(`${key}[]`, item);
                        });
                    } else {
                        params.append(key, value);
                    }
                }
                fetchOptions.body = params.toString();
            } else {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify(data);
            }
        }else if(method === 'GET'){
            url = url + '?' + pwBuildQueryString(data);
        }

        const response = await fetch(pwBaseUrl + url, fetchOptions);

        const contentType = response.headers.get('content-type');
        const responseData = contentType?.includes('application/json')
            ? await response.json()
            : await response.text();

        if (!response.ok) {
            throw new Error(responseData.message || 'خطا در پاسخ از سرور');
        }

        return responseData;
    } catch (err) {
        console.error('API error:', err.message);
        pwNotyf.error('در پردازش درخواست خطایی رخ داده است!');
        throw err;
    }
}

let pwRangeDateFrom = null;
let pwRangeDateTo = null;
function pwCreateRangeDateFilter(parentEl, dateFromValue, dateToValue) {

    if(!window?.$){
        window.$ = window.jQuery;
    }

    pwRangeDateFrom?.destroy();
    pwRangeDateTo?.destroy();

    pwRangeDateFrom = $($(parentEl).find(".range-date-from")).persianDatepicker({
        initialValueType: 'persian',
        inline: true,
        altField: '.range-date-from-alt',
        leapYearMode: 'astronomical',
        altFormat: 'L',
        initialValue: true,
        maxDate: dateToValue,
        toolbox: {
            enabled: false
        },
        navigator: {
            scroll: {
                enabled: false
            }
        },
        calendar:{
            persian: {
                leapYearMode: 'astronomical'
            }
        },
        onSelect: (unix) => {
            if (pwRangeDateTo && pwRangeDateTo.options && pwRangeDateTo.options.minDate != unix) {
                let cachedValue = pwRangeDateTo.getState().selected.unixDate;
                pwRangeDateTo.options = {minDate: unix};
                pwRangeDateTo.setDate(cachedValue);
            }
        }
    });
    pwRangeDateFrom.setDate(dateFromValue)

    pwRangeDateTo = $($(parentEl).find(".range-date-to")).persianDatepicker({
        initialValueType: 'persian',
        inline: true,
        altField: '.range-date-to-alt',
        altFormat: 'L',
        minDate: dateFromValue,
        initialValue: true,
        toolbox: {
            enabled: false
        },
        navigator: {
            scroll: {
                enabled: false
            }
        },
        calendar:{
            persian: {
                leapYearMode: 'astronomical'
            }
        },
        onSelect: (unix) => {
            if (pwRangeDateFrom && pwRangeDateFrom.options && pwRangeDateFrom.options.maxDate != unix) {
                let cachedValue = pwRangeDateFrom.getState().selected.unixDate;
                pwRangeDateFrom.options = {maxDate: unix};
                pwRangeDateFrom.setDate(cachedValue);
            }
        }
    });
    pwRangeDateTo.setDate(dateToValue)

    return [pwRangeDateFrom, pwRangeDateTo];
}

function pwCreateQuickSelectDateItems(today){

    const arrayItems = [
        {
            type: 'today',
            label: 'امروز',
            from: today,
            to: today,
            previousFrom: today.add('days', -1),
            previousTo: today.add('days', -1)
        },
        {
            type: 'yesterday',
            label: 'دیروز',
            from: today.add('days', -1),
            to: today.add('days', -1),
            previousFrom: today.add('days', -2),
            previousTo: today.add('days', -2)
        },
        {
            type: 'week',
            label: 'هفته تا امروز',
            from: today.startOf('week'),
            to: today,
            previousFrom: today.startOf('week').add('days', -7),
            previousTo: today.add('days', -7)
        },
        {
            type: 'last-week',
            label: 'هفته گدشته',
            from: today.add('week', -1).startOf('week'),
            to: today.add('week', -1).endOf('week'),
            previousFrom: today.add('week', -2).startOf('week'),
            previousTo: today.add('week', -2).endOf('week')
        },
        {
            type: 'month',
            label: 'ماه تا امروز',
            from: today.startOf('month'),
            to: today,
            previousFrom:  today.add('month', -1).startOf('month'),
            previousTo: today.add('month', -1).startOf('month').add('days', today.date() - 2)
        },
        {
            type: 'last-month',
            label: 'ماه گذشته',
            from: today.add('month', -1).startOf('month'),
            to: today.add('month', -1).endOf('month'),
            previousFrom: today.add('month', -2).startOf('month'),
            previousTo: today.add('month', -2).endOf('month')
        },
    ]

    switch (today.month()){
        case 1: case 4: case 7: case 10: {

            arrayItems.push({
                type: 'season',
                label: 'فصل تا امروز',
                from: today.startOf('month'),
                to: today,
                previousFrom: today.add('month', -3).startOf('month'),
                previousTo:  today.add('month', -3).startOf('month').add('days', today.date() - 2),
            })

            arrayItems.push({
                type: 'last-season',
                label: 'فصل گذشته',
                from: today.startOf('month').add('month', -3),
                to: today.startOf('month').add('month', -1).endOf('month'),
                previousFrom: today.add('month', -6).startOf('month'),
                previousTo: today.add('month', -4).endOf('month'),
            })

            break;
        }

        case 2: case 5: case 8: case 11: {

            arrayItems.push({
                type: 'season',
                label: 'فصل تا امروز',
                from: today.add('month', -1).startOf('month'),
                to: today,
                previousFrom: today.add('month', -4).startOf('month'),
                previousTo:  today.add('month', -3).startOf('month').add('days', today.date() - 2),
            })

            arrayItems.push({
                type: 'last-season',
                label: 'فصل گذشته',
                from: today.startOf('month').add('month', -4),
                to:  today.startOf('month').add('month', -2).endOf('month'),
                previousFrom: today.startOf('month').add('month', -7),
                previousTo: today.add('month', -5).startOf('month').endOf('month'),
            })
            break;
        }

        case 3: case 6: case 9: case 12: {

            arrayItems.push({
                type: 'season',
                label: 'فصل تا امروز',
                from: today.add('month', -2).startOf('month'),
                to: today,
                previousFrom: today.add('month', -5).startOf('month'),
                previousTo:  today.add('month', -3).startOf('month').add('days', today.date() - 2),
            })

            arrayItems.push({
                type: 'last-season',
                label: 'فصل گذشته',
                from: today.startOf('month').add('month', -5),
                to:  today.startOf('month').add('month', -3).endOf('month'),
                previousFrom: today.add('month', -8).startOf('month'),
                previousTo: today.add('month', -6).startOf('month').endOf('month'),
            })
            break;
        }
    }

    arrayItems.push({
        type: 'year',
        label: 'سال تا امروز',
        from: today.startOf('year'),
        to: today,
        previousFrom:  today.add('year', -1).startOf('year'),
        previousTo: today.add('year', -1).startOf('year').add('days', Number(pwConvertPersianNumberToEnglish(today.format("DDD"))) - 2)
    });
    arrayItems.push({
        type: 'last-year',
        label: 'سال گذشته',
        from: today.add('year', -1).startOf('year'),
        to: today.add('year', -1).endOf('year'),
        previousFrom: today.add('year', -2).startOf('year'),
        previousTo: today.add('year', -2).endOf('year'),
    });

    return arrayItems
}

//create chart
let pwChartCreated = null;
function pwCreateChart(parentEl, data){
    const gradient = window['chartjs-plugin-gradient'];
    Chart.defaults.font.family = "'YekanBakhFaNum', 'Vazirmatn', serif";
    Chart.register(gradient);

    const canvasEl = parentEl.querySelector('canvas');
    if(pwChartCreated){
        pwChartCreated.destroy();
    }

    pwChartCreated = new Chart(canvasEl, {
        type: 'line',
        data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                gradient,
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    min: 0
                }
            },
            elements: {
                point: {
                    radius: 1,
                    hoverRadius: 6,
                    hoverBorderWidth: 3,
                    borderWidth: 2
                },
            }
        }
    });
}