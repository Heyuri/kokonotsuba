// location of banners
//var baseUrl = "https://static.heyuri.net/image/banner/"; // you can just use URL like this
var baseUrl = "./static/image/banner/";

// Array of banner filenames
var randomImage = [
    "banner003.gif",
    "banner002.jpg",
    "banner001.png"
];

function getRandomImage() { 
    var number = Math.floor(Math.random() * randomImage.length);
    document.write('<img border="1" src="' + baseUrl + randomImage[number] + '" id="banner" style="max-width: 300px;" title="Click to change" onclick="change()" />');
}
getRandomImage();

function change() {
    var num = Math.floor(Math.random() * randomImage.length);
    var imgUrl = baseUrl + randomImage[num];
    document.getElementById("banner").src = imgUrl;
}
