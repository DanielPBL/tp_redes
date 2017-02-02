"use strict";
var fs = require('fs');
var rl = require('readline');

var Rota = function(net_ip, mask, gate) {
    var _this = this;

    net_ip  = Buffer.from(String(net_ip).split('.'));
    mask    = Buffer.from(String(mask).split('.'));
    gate    = String(gate).split(':');
    gate[1] = String(gate[1]).split('/');

    _this.ipRede    = net_ip.readUInt32BE();
    _this.mascara   = mask.readUInt32BE();
    _this.interface = {
        'TMQ': 512,
        'ip' : String(gate[0]),
        'sr' : Number(gate[1][0]),
        'cl' : Number(gate[1][1]),
        'ij' : Number(gate[1][2])
    };
};

Rota.prototype.match = function(ip_addr) {
    var endIP = Buffer.from(String(ip_addr).split('.')).readUInt32BE();

    if ((endIP & this.mascara) === this.ipRede)
        return this.interface;
    else
        return false;
};

var Tabela = function(cb, path) {
    var _this = this;

    _this.rotas  = [];
    _this.cb     = cb;

    if (typeof(path) === 'string')
        _this.load(path);
};

Tabela.prototype.load = function(path) {
    var _this = this;

    var lineReader = rl.createInterface({
        input: fs.createReadStream(path)
    });

    lineReader.on('line', function(linha) {
        linha = String(linha).split(' ');

        if (linha.length === 3)
            _this.rotas.push(new Rota(linha[0], linha[1], linha[2]));
    });

    lineReader.on('close', function() {
        if (typeof(_this.cb) === 'function')
            _this.cb();
    });
};

Tabela.prototype.match = function(ip_addr) {
    var match = false;

    for (var rota of this.rotas) {
        match = rota.match(ip_addr);
        if (match !== false)
            break;
    }

    return match;
};

exports.Rota = Rota;
exports.Tabela = Tabela;
