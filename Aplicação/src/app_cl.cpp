#include <stdio.h>
#include <stdlib.h>
#include <iostream>

#include <netdb.h>
#include <netinet/in.h>

#include <string.h>
#include <unistd.h>

#include "camadas.h"

using namespace std;

#define PORTA_SERVER 8081
#define PORTA_LOCAL 8083
#define ENDERECO "127.0.0.1"


int main(int argc, char **argv) {
   int sockfd, portno, n, newsockfd;
   struct sockaddr_in serv_addr,cli_addr;
   struct hostent *server;
   socklen_t clilen;
   

   char buffer[1024];

   portno = PORTA_SERVER;

   /* Create a socket point */
   sockfd = socket(AF_INET, SOCK_STREAM, 0);

   if (sockfd < 0) {
      perror("ERROR opening socket");
      exit(1);
   }

   server = gethostbyname(ENDERECO);

   if (server == NULL) {
      fprintf(stderr,"ERROR, no such host\n");
      exit(0);
   }

   bzero((char *) &serv_addr, sizeof(serv_addr));
   serv_addr.sin_family = AF_INET;
   bcopy((char *)server->h_addr, (char *)&serv_addr.sin_addr.s_addr, server->h_length);
   serv_addr.sin_port = htons(portno);

   /* Now connect to the server */
   if (connect(sockfd, (struct sockaddr*)&serv_addr, sizeof(serv_addr)) < 0) {
      perror("ERROR connecting");
      exit(1);
   }

   /* Now ask for a message from the user, this message
      * will be read by server
   */

   printf("Digite o servidor/pagina que deseja acessar: ");
   cin >> buffer;
   // Faz a requisição
   char req[1024] = "GET / HTTP/1.1\r\nHost: ";
    strcat(req, buffer);
    strcat(req, "\r\n");
    strcat(req, "User-Agent: Mozilla/5.0\r\n");
    strcat(req, "Accept: text/html\r\n");
    strcat(req, "Accept-Language: pt-BR,pt\r\n");
    strcat(req, "Accept-Encoding: gzip, deflate\r\n");

   char *msg_resposta = prepara_mensagem(req);

   /* Send message to the server */
   n = write(sockfd, msg_resposta, strlen(req) + 52);

   delete[] msg_resposta;

   if (n < 0) {
      perror("ERROR writing to socket");
      exit(1);
   }

   close(sockfd);
   
   

   /*******Aguardando conexao para o retorno com o html***********/
   /* First call to socket() function */
   sockfd = socket(AF_INET, SOCK_STREAM, 0);

   if (sockfd < 0) {
      perror("ERROR opening socket 2");
      exit(1);
   }
   
   bzero((char *) &serv_addr, sizeof(serv_addr));
    portno = PORTA_LOCAL;
   serv_addr.sin_family = AF_INET;
   serv_addr.sin_addr.s_addr = INADDR_ANY;
   serv_addr.sin_port = htons(portno);

   /* Now bind the host address using bind() call.*/
   if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0) {
      perror("ERROR on binding 2");
      exit(1);
   }

   
   cout << "cliente aguardando resposta do servidor...\n";

   listen(sockfd, 5);
   clilen = sizeof(cli_addr);

   while (1) {
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

       printf("Resposta do servidor: %s\n", buffer + 51);
       
        printf("%s\n",buffer);

       close(newsockfd);
       
   }
    return 0;

}
