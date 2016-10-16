#include <stdio.h>
#include <stdlib.h>
#include <iostream>

#include <netdb.h>
#include <netinet/in.h>

#include <string.h>
#include <unistd.h>

#include "camadas.h"

using namespace std;
FILE *openfile;

#define PORTA 8082

int main(int argc, char **argv) {
    int sockfd, newsockfd, portno, sockfd_r;
    socklen_t clilen;
    struct hostent *server;
    char buffer[1024];
    char resultcode[50], fileserver[50];
    struct sockaddr_in serv_addr, cli_addr;
    int  n;

    if (argc < 3) {
        printf("Execução: ./app_sr porta_escutada porta_resposta\n");
        exit(1);
    }

    /* First call to socket() function */
    sockfd = socket(AF_INET, SOCK_STREAM, 0);

    if (sockfd < 0) {
        perror("ERROR opening socket");
        exit(1);
    }

    /* Initialize socket structure */
    bzero((char *) &serv_addr, sizeof(serv_addr));
    portno = atoi(argv[1]);

    serv_addr.sin_family = AF_INET;
    serv_addr.sin_addr.s_addr = INADDR_ANY;
    serv_addr.sin_port = htons(portno);

    /* Now bind the host address using bind() call.*/
    if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0) {
        perror("ERROR on binding");
        exit(1);
    }

    /* Now start listening for the clients, here process will
    * go in sleep mode and will wait for the incoming connection
    */
    listen(sockfd, 5);
    clilen = sizeof(cli_addr);

    while (1) {
        cout << "Aguardando requisição...\n";
        /* Accept actual connection from the client */
        newsockfd = accept(sockfd, (struct sockaddr *)&cli_addr, &clilen);

        if (newsockfd < 0) {
            perror("ERROR on accept");
            exit(1);
        }

        /* If connection is established then start communicating */
        bzero(buffer, 1024);
        n = read(newsockfd, buffer, 1024);

        if (n < 0) {
            perror("ERROR reading from socket");
            exit(1);
        }

        /****METODO DA REQUISIÇAO**/
        char *metodo  = strtok(buffer + 52, " ");
        printf("Método: %s\n", metodo);

        /****FILE SERVER**/
        strcpy(fileserver, strtok(NULL, " "));

        if (strcmp(fileserver, "/") == 0 ) //arquivo padrao do servidor
            strcpy(fileserver, "/index.html");

        /****HOST SERVER**/
        strtok(NULL, "\r\n");
        char *hostname = strtok(NULL, "\r\n");
        strtok(hostname, " ");
        hostname = strtok(NULL, "");

        cout << "Host: " << hostname << endl;
        cout << "Arquivo: " << fileserver << endl;

        /***Le arquivo html no servidor**/
        string caminho = string(".") + string(fileserver);

        openfile = fopen (caminho.c_str(), "r");
        if (openfile) {
            strcpy(resultcode, "200 OK");
        } else {
            strcpy(fileserver, "404.html");
            strcpy(resultcode, "404 Not Found");
            openfile = fopen (fileserver, "r");
        }

        char  linha[300] = "", html[1024] = "";

        while (fgets(linha, 300, openfile) != NULL)
            strcat(html, linha);          // append the new data

        fclose(openfile);

        close(newsockfd);

        /* First call to socket() function */
        sockfd_r = socket(AF_INET, SOCK_STREAM, 0);

        if (sockfd_r < 0) {
            perror("ERROR opening socket 2");
            exit(1);
        }

        server = gethostbyname("127.0.0.1");

        if (server == NULL) {
            fprintf(stderr, "ERROR, no such host\n");
            exit(1);
        }

        portno = atoi(argv[2]);

        bzero((char *) &serv_addr, sizeof(serv_addr));
        serv_addr.sin_family = AF_INET;
        bcopy((char *)server->h_addr, (char *)&serv_addr.sin_addr.s_addr, server->h_length);
        serv_addr.sin_port = htons(portno);

        /* Now connect to the server */
        if (connect(sockfd_r, (struct sockaddr*)&serv_addr, sizeof(serv_addr)) < 0) {
            perror("ERROR connecting 2");
            exit(1);
        }

        /****Monta o resultado para retorno******************/
        char result[1024] = "HTTP/1.1 ";
        strcat(result, resultcode);
        strcat(result, "\r\n");
        strcat(result, "Location: http://");
        strcat(result, hostname);
        strcat(result, "\r\n");

        strcat(result, "Date: ");
        strcat(result, __DATE__);
        strcat(result, " ");
        strcat(result, __TIME__);
        strcat(result, "\r\n");

        strcat(result, "Server: Apache/2.2.22\r\n");

        strcat(result, "Content-Type: text/html\r\n");
        //strcat(result, tipo);

        //strcat(result, "Content-Length: ");
        //strcat(result, fileLength);
        //strcat(result, "\r\n");

        strcat(result, "Connection: close\r\n");

        /***Conteudo HTML****/
        strcat(result, "\r\n");
        strcat(result, html);

        cout << "Resposta para a requisição:" << endl;
        cout << result << endl;

        /* Write a response to the client */
        char *msg_resposta = prepara_mensagem(result);

        n = write(sockfd_r, msg_resposta, strlen(result) + 52);

        delete[] msg_resposta;

        if (n < 0) {
            perror("ERROR writing to socket");
            exit(1);
        }

        close(sockfd_r);
    }

    return 0;
}
