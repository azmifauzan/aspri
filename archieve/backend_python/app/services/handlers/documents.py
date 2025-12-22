from typing import Any, Dict


class DocumentsHandler:
    def __init__(self, svc: Any):
        self.svc = svc

    async def document_search(self, user_id: int, query: str, user_info: Dict[str, Any]) -> str:
        try:
            self.svc.print(f"Handling document search for user {user_id} with query: {query}")
            document_service = self.svc.DocumentService(self.svc.db)
            search_query = self.svc.DocumentSearchQuery(query=query, limit=5)
            search_results = await document_service.search_documents(user_id, search_query)

            if not search_results:
                return "I couldn't find any relevant information in your documents for that query."

            formatted_results = "\n\n".join([
                f"Document: {result['document_filename']}\n"
                f"Relevance: {result['similarity_score']:.2f}\n"
                f"Content: {result['chunk_text']}"
                for result in search_results
            ])

            prompt = self.svc.document_search_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                user_name=user_info.get("name", "User"),
                call_preference=user_info.get("call_preference", "User"),
                search_results=formatted_results,
                user_query=query
            )

            response = self.svc.chat_model.invoke([self.svc.HumanMessage(content=prompt)])

            await self.svc.llm_log_service.create_log(
                prompt_type="chat_response",
                prompt_data={"user_query": query, "search_results": formatted_results},
                llm_response=response.content,
                user_id=user_id
            )

            return response.content
        except Exception as e:
            self.svc.print(f"Error handling document search: {e}")
            return "I encountered an error while searching your documents. Please try rephrasing your query."

    async def summarize_specific_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data or 'document_name' not in data:
                return "Please specify the name of the document you want to summarize."

            document_name = data['document_name']
            document_service = self.svc.DocumentService(self.svc.db)

            document = await document_service.get_document_by_filename_and_user_id(document_name, user_id)
            if not document:
                return f"I couldn't find a document named '{document_name}'."

            minio_service = self.svc.MinIOService()
            try:
                content_bytes = await minio_service.get_file(document.minio_object_name)
                document_content = content_bytes.decode('utf-8')
            except Exception as e:
                self.svc.print(f"Error fetching document content from MinIO: {e}")
                return "I'm sorry, I encountered an error while retrieving the document content."

            prompt = self.svc.summarize_document_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                document_name=document_name,
                document_content=document_content
            )
            response = self.svc.chat_model.invoke([self.svc.HumanMessage(content=prompt)])

            await self.svc.llm_log_service.create_log(
                prompt_type="summarize_document_response",
                prompt_data={"document_name": document_name, "document_content": document_content},
                llm_response=response.content,
                user_id=user_id
            )

            return response.content
        except Exception as e:
            self.svc.print(f"Error handling summarize specific document: {e}")
            return "I'm sorry, I encountered an error while summarizing the document."

    async def compare_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data or 'document_names' not in data or len(data['document_names']) < 2:
                return "Please specify at least two documents to compare."

            doc_names = data['document_names']
            document_service = self.svc.DocumentService(self.svc.db)
            minio_service = self.svc.MinIOService()

            document_contents = []
            for name in doc_names:
                document = await document_service.get_document_by_filename_and_user_id(name, user_id)
                if not document:
                    return f"I couldn't find a document named '{name}'."
                try:
                    content_bytes = await minio_service.get_file(document.minio_object_name)
                    document_contents.append({
                        "name": name,
                        "content": content_bytes.decode('utf-8')
                    })
                except Exception as e:
                    self.svc.print(f"Error fetching document content for '{name}' from MinIO: {e}")
                    return f"I'm sorry, I encountered an error while retrieving the content of '{name}'."

            document_comparisons = "\n\n---\n\n".join(
                f"Document: {item['name']}\nContent:\n{item['content']}"
                for item in document_contents
            )

            prompt = self.svc.compare_documents_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                document_comparisons=document_comparisons
            )
            response = self.svc.chat_model.invoke([self.svc.HumanMessage(content=prompt)])

            await self.svc.llm_log_service.create_log(
                prompt_type="compare_documents_response",
                prompt_data={"document_comparisons": document_comparisons},
                llm_response=response.content,
                user_id=user_id
            )

            return response.content
        except Exception as e:
            self.svc.print(f"Error handling compare document: {e}")
            return "I'm sorry, I encountered an error while comparing the documents."
