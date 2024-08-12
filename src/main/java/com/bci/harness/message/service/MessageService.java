package com.bci.harness.message.service;

import com.bci.harness.message.entity.Message;

import java.util.List;

public interface MessageService {
    long getInsultCount();

    <S extends Message> List<S> saveAll(Iterable<S> entities);

    Message findByContent(String content);

    public <S extends Message> S save(S entity);
}
