class Scaner {
    constructor() {
        console.log('constructor');
        this.loadText();
    }

    loadText() {
        console.log('loadText');
    }
};

var scaner = new Scaner();
$('.code_area').on('click', function () {
    alert('bind');
    console.log('keyup');
});