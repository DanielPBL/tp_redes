"use strict";

var args = process.argv.slice(2);
console.log(args);

var net     = require('net');
var hexdump = require('hexdump-nodejs');
var rts     = require('./rota.js');

var server = net.createServer(function(socket) {
    socket.write('Echo server\n');

    socket.on('error', function(error) {
        console.log(error.name + ': ' + error.message);
    });

    socket.pipe(socket);
});

server.on('error', function(error) {
    console.log(error.name + ': ' + error.message);
});

server.listen(1337, '127.0.0.1');
