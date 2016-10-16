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
   int sockfd, newsockfd, portno;
   socklen_t clilen;
   char buffer[1024];
   char *lines;
   char resultcode[50], *fileserver;
   struct sockaddr_in serv_addr, cli_addr;
   int  n;

   /* First call to socket() function */
   sockfd = socket(AF_INET, SOCK_STREAM, 0);

   if (sockfd < 0) {
      perror("ERROR opening socket");
      exit(1);
   }

   /* Initialize socket structure */
   bzero((char *) &serv_addr, sizeof(serv_addr));
   portno = PORTA;

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
   cout << "aguardando conexao...\n";

   listen(sockfd, 5);
   clilen = sizeof(cli_addr);

   while (1) {
     cout << "Servidor rodando...\n";
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
		               
	cout << "Requisicao recebida...\n";
	/*
	/****METODO DA REQUISIÇAO**/
	char *metodo  = strtok(buffer+51, "\r\n");
	printf( " %s\n", metodo );
	
	/****HOST SERVER**/
	char *hostname  = strtok(NULL, "\r\n");	
	hostname = strtok(hostname, " ");
    	hostname = strtok(NULL, " ");
	printf( "Host %s\n", hostname );
	
	/****FILE SERVER**/
	/*int tam_host = strlen(hostname);
	fileserver = NULL; 
	for(int i=0; i < tam_host;i++)
	  if(strcmp(hostname+i,"/" ) == 0) Esta dando um problema ao fazer essa verificaçao, entao considerei que o usuario sempre vai digitar a barra, por enquanto*/ 
	    fileserver = strtok(hostname, "/");
	  
	fileserver = strtok(NULL, " ");
	printf( " %s\n", fileserver );	
	
	/***Le arquivo html no servidor**/
	if (strlen(fileserver) < 1 ) //arquivo padrao do servidor
            strcpy(fileserver, "index.html");
	
	openfile = fopen (fileserver, "r");
	if (openfile){
	  printf( "Abriu o arquivo: %s\n",fileserver);  
	  strcpy(resultcode, "200 OK");
	  printf( "Resultado 200 OK \n");  
	  
	} else {
	  printf( "arquivo nao existe\n");
	  strcpy(fileserver, "404.html");
	  strcpy(resultcode, "404 Not Found");
	  openfile = fopen (fileserver, "r");
	  if (openfile){
	    printf( "abriu o arquivo \n");  
	  }
	}
	
	
	printf( "tentou abrir o arquivo: %s\n", fileserver );
	
	char  linha[300] = "", html[1024] = "";
	while (fgets(linha,300, openfile)!= NULL)
	{        
	    strcat(html, linha);          // append the new data
	}
	fclose(openfile);

	
	

	/****Monta o resultado para retorno******************/
	char result[1024];
	strcpy(result, "Location: http://");
	strcat(result, hostname);
	strcat(result, "\r\n");
	
	strcat(result, "Date: ");
	strcat(result, __DATE__);
	strcat(result, __TIME__);
	strcat(result, "\r\n");
	
	strcat(result, "Server: servidorteste/1.0\r\n");
	
	strcat(result, "Content-Type: text/html\r\n");
	//strcat(result, tipo);
	
	strcat(result, "Content-Length: ");
	//strcat(result, fileLength);
	strcat(result, "\r\n");
	
	strcat(result, "Connection: close\r\n\r\n");
	
	
	strcat(result, "\r\n");
	
	/***Conteudo HTML****/
	strcat(result, "\r\n");
	strcat(result, html);
	
	
	/*
	 * falta pegar os outros parametros
	char *agent = strtok(NULL, "\r\n");
	printf( " %s\n", agent );
	agent = strtok(agent, " ");
	printf( " %s\n", agent );
	agent = strtok(agent, " ");
	printf( " %s\n", agent );
	
	*/
	
    	

       /* Write a response to the client */       
       char *msg_resposta = prepara_mensagem(result);

       n = write(newsockfd, msg_resposta, strlen(result) + 52);

       delete[] msg_resposta;

       if (n < 0) {
          perror("ERROR writing to socket");
          exit(1);
       }

       close(newsockfd);
       cout << "Requisicao respondida...\n";
   }

   return 0;
}
